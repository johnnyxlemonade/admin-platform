<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\InternalAdminDestination;
use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\MfaLoginChallengeStore;
use Lemonade\Admin\Auth\Mfa\MfaRecentPrimaryAuthenticationStore;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpProvisioningUriFactory;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje anonymni challenge i self-service local TOTP lifecycle
 */
final class MfaController
{
    /**
     * Nastavuje MFA workflow, aktualni identitu a auth presentation zavislosti
     */
    public function __construct(
        private readonly AdminMfaConfiguration $configuration,
        private readonly MfaAuthenticationService $mfa,
        private readonly TotpCredentialService $credentials,
        private readonly MfaLoginChallengeStore $challenges,
        private readonly MfaRecentPrimaryAuthenticationStore $recentPrimary,
        private readonly AuthenticationService $authentication,
        private readonly CurrentUserProvider $currentUser,
        private readonly TotpProvisioningUriFactory $provisioningUris,
        private readonly AdminUiLocale $uiLocale,
        private readonly ClientTranslationVersion $translationVersion,
        private readonly AdminBranding $branding,
        private readonly AdminRoutingConfiguration $routing,
        private readonly AuthPageRenderer $pages,
        private readonly TranslatorInterface $translator,
        private readonly Responses $responses,
        private readonly ViewRendererInterface $views,
    ) {}

    /**
     * Zobrazi anonymni TOTP challenge pouze pro platny pending login
     */
    public function challenge(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();
        if (!$this->configuration->enabled() || $challenge === null) {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        if ($challenge['kind'] === 'enroll') {
            return $this->responses->redirect($this->routing->path('/login/mfa/enroll'));
        }

        return $this->render($request, 'admin-auth::mfa', ['errorKey' => null]);
    }

    /**
     * Overi challenge kod a teprve potom zalozi canonical identity session
     */
    public function verifyChallenge(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();
        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'verify') {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        $code = (string) (new RequestData($request))->post('code', '');
        $user = $this->mfa->eligibleUser($challenge['userId']);
        if ($user === null || !$this->credentials->verifyAndConsume($challenge['userId'], $code)) {
            if ($this->challenges->failedAttempt()) {
                $this->mfa->challengeFailed($challenge['userId']);

                return $this->responses->redirect($this->routing->path('/login'));
            }

            return $this->render($request, 'admin-auth::mfa', ['errorKey' => 'auth.mfa.errors.invalid'], 422);
        }

        $this->challenges->clear();
        $this->authentication->login($user, true);

        return $this->responses->redirect($this->safeDestination($challenge['intendedPath']));
    }

    /**
     * Zobrazi forced enrollment jako soucast dosud neautentizovaneho loginu
     */
    public function loginEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();
        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'enroll') {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        return $this->renderEnrollment($request, $challenge['userId'], false);
    }

    /**
     * Zalozi nebo nahradi forced enrollment secret bez zalozeni user session
     */
    public function startLoginEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();
        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'enroll') {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        return $this->renderEnrollment($request, $challenge['userId'], false, true);
    }

    /**
     * Potvrdi forced enrollment, znovu overi lifecycle a dokonci canonical login
     */
    public function confirmLoginEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();
        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'enroll') {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        if (!$this->credentials->confirmEnrollment($challenge['userId'], (string) (new RequestData($request))->post('code', ''))) {
            return $this->renderEnrollment($request, $challenge['userId'], false, false, 'auth.mfa.errors.invalid', 422);
        }
        $user = $this->mfa->eligibleUser($challenge['userId']);
        if ($user === null) {
            $this->challenges->clear();

            return $this->responses->redirect($this->routing->path('/login'));
        }

        $this->mfa->enrolled($user->id());
        $this->challenges->clear();
        $this->authentication->login($user, true);

        return $this->responses->redirect($this->safeDestination($challenge['intendedPath']));
    }

    /**
     * Vraci obsah self-service MFA modalu pouze pro aktualniho prihlaseneho uzivatele
     */
    public function modal(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        return $this->modalResponse();
    }

    /**
     * Overi aktualni local password pred citlivou MFA zmenou v modalu
     */
    public function reauthenticateModal(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->currentUser();
        $password = (string) (new RequestData($request))->post('password', '');
        if ($user === null || $this->authentication->authenticateLocal($user->email(), $password)->user()?->id() !== $user->id()) {
            return $this->modalResponse(errorKey: 'auth.errors.invalid');
        }

        $this->recentPrimary->remember($user->id());

        return $this->modalResponse();
    }

    /**
     * Vytvori novy TOTP secret jen po cerstvem potvrzeni primarni autentizace
     */
    public function startModalEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->currentUser();
        if ($user === null || !$this->recentPrimary->isRecentFor($user->id())) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.reauthenticate_required');
        }

        $secret = $this->credentials->beginEnrollment($user->id());

        return $this->modalResponse(secret: $secret->toBase32(), provisioningUri: $this->provisioningUris->create($this->configuration->issuer ?? '', $user->email(), $secret, new TotpConfiguration()));
    }

    /**
     * Potvrdi nove MFA nastaveni pouze pro aktualniho uzivatele po cerstvem hesle
     */
    public function confirmModalEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->currentUser();
        if ($user === null || !$this->recentPrimary->isRecentFor($user->id())) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.reauthenticate_required');
        }
        if (!$this->credentials->confirmEnrollment($user->id(), (string) (new RequestData($request))->post('code', ''))) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.invalid');
        }

        $this->mfa->enrolled($user->id());

        return $this->modalResponse(noticeKey: 'auth.mfa.enrolled');
    }

    /**
     * Zrusi vlastni credential pouze v optional policy a po cerstvem hesle
     */
    public function disableModal(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->currentUser();
        if ($user === null || $this->configuration->localRequirement !== 'optional' || !$this->recentPrimary->isRecentFor($user->id())) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.disable_not_allowed');
        }

        $this->credentials->revoke($user->id());
        $this->mfa->disabled($user->id());

        return $this->modalResponse(noticeKey: 'auth.mfa.disabled');
    }

    /**
     * Vykresli enrollment s novym secret pouze pri explicitnim startu
     */
    private function renderEnrollment(ServerRequestInterface $request, int $userId, bool $profile, bool $start = false, ?string $errorKey = null, int $status = 200): ResponseInterface
    {
        $secret = $start ? $this->credentials->beginEnrollment($userId) : null;
        $user = $this->mfa->eligibleUser($userId);
        if ($user === null) {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        return $this->render($request, 'admin-auth::mfa-enroll', [
            'profile' => $profile,
            'secret' => $secret?->toBase32(),
            'provisioningUri' => $secret === null ? null : $this->provisioningUris->create($this->configuration->issuer ?? '', $user->email(), $secret, new TotpConfiguration()),
            'errorKey' => $errorKey,
        ], $status);
    }

    /**
     * Vykresli auth layout s MFA lokalizaci a brandingem
     *
     * @param array<string,mixed> $data
     */
    private function render(ServerRequestInterface $request, string $view, array $data, int $status = 200): ResponseInterface
    {
        return $this->pages->render($view, [
            ...$data,
            'title' => $this->branding->applicationName . ' – ' . $this->translator->get('auth.mfa.title'),
            'locale' => $this->uiLocale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', '')),
            'clientTranslationVersion' => $this->translationVersion,
        ], $status);
    }

    /**
     * Sestavi JSON odpoved s obsahem sdileneho Admin modalu
     */
    private function modalResponse(?string $errorKey = null, ?string $noticeKey = null, ?string $secret = null, ?string $provisioningUri = null): ResponseInterface
    {
        $user = $this->currentUser->currentUser();
        if (!$this->configuration->enabled() || $user === null) {
            return $this->responses->json(['success' => false], 403);
        }

        return $this->responses->json([
            'success' => true,
            'modalHtml' => $this->views->content('admin-auth::mfa-modal', [
                'active' => $this->credentials->hasActiveCredential($user->id()),
                'recent' => $this->recentPrimary->isRecentFor($user->id()),
                'canDisable' => $this->configuration->localRequirement === 'optional',
                'errorKey' => $errorKey,
                'noticeKey' => $noticeKey,
                'secret' => $secret,
                'provisioningUri' => $provisioningUri,
            ]),
        ]);
    }

    /**
     * Vraci pouze provereny interni cil nebo root administrace
     */
    private function safeDestination(?string $destination): string
    {
        return is_string($destination) && InternalAdminDestination::isSafe($destination, $this->routing)
            ? $destination
            : $this->routing->basePath;
    }
}
