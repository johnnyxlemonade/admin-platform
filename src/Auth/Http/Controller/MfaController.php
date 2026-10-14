<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthenticationThrottler;
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
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpProvisioningUriFactory;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje anonymni challenge i self-service local TOTP lifecycle
 */
final readonly class MfaController
{
    /**
     * Nastavuje MFA workflow, aktualni identitu a auth presentation zavislosti
     */
    public function __construct(
        private AdminMfaConfiguration $configuration,
        private MfaAuthenticationService $mfa,
        private TotpCredentialService $credentials,
        private MfaLoginChallengeStore $challenges,
        private MfaRecentPrimaryAuthenticationStore $recentPrimary,
        private AuthenticationService $authentication,
        private AuthenticationThrottler $throttler,
        private CurrentUserProvider $currentUser,
        private TotpProvisioningUriFactory $provisioningUris,
        private AdminUiLocale $uiLocale,
        private ClientTranslationVersion $translationVersion,
        private AdminBranding $branding,
        private AdminRoutingConfiguration $routing,
        private AuthPageRenderer $pages,
        private TranslatorInterface $translator,
        private Responses $responses,
        private CsrfViewHelper $csrf,
        private HttpRequestInspector $requests,
        private ViewRendererInterface $views,
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

        return $this->render($request, 'admin-auth::mfa', ['errorKey' => null, 'errorMessage' => null]);
    }

    /**
     * Overi challenge kod a teprve potom zalozi canonical identity session
     */
    public function verifyChallenge(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();
        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'verify') {
            return $this->redirect($request, $this->routing->path('/login'));
        }

        $limit = $this->throttler->mfaStatus($request, $challenge['userId']);
        if (!$limit->allowed()) {
            $this->challenges->clear();

            return $this->throttledChallenge($request, $limit->retryAfterSeconds());
        }

        $code = (string) (new RequestData($request))->post('code', '');
        $user = $this->mfa->eligibleUser($challenge['userId']);
        if ($user === null || !$this->credentials->verifyAndConsume($challenge['userId'], $code)) {
            $limit = $this->throttler->failedMfa($request, $challenge['userId']);
            if (!$limit->allowed()) {
                $this->challenges->clear();
                $this->mfa->challengeFailed($challenge['userId']);

                return $this->throttledChallenge($request, $limit->retryAfterSeconds());
            }

            return $this->failedChallenge($request);
        }

        $this->challenges->clear();
        $this->throttler->clearMfaUser($challenge['userId']);
        $this->authentication->login($user, true);

        return $this->redirect($request, $this->safeDestination($challenge['intendedPath']));
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

        $limit = $this->throttler->mfaStatus($request, $challenge['userId']);
        if (!$limit->allowed()) {
            $this->challenges->clear();

            return $this->throttledEnrollment($request, $challenge['userId'], $limit->retryAfterSeconds());
        }

        if (!$this->credentials->confirmEnrollment($challenge['userId'], (string) (new RequestData($request))->post('code', ''))) {
            $limit = $this->throttler->failedMfa($request, $challenge['userId']);
            if (!$limit->allowed()) {
                $this->challenges->clear();
                $this->mfa->challengeFailed($challenge['userId']);

                return $this->throttledEnrollment($request, $challenge['userId'], $limit->retryAfterSeconds());
            }

            return $this->renderEnrollment($request, $challenge['userId'], false, false, 'auth.mfa.errors.invalid', 422);
        }
        $user = $this->mfa->eligibleUser($challenge['userId']);
        if ($user === null) {
            $this->challenges->clear();

            return $this->responses->redirect($this->routing->path('/login'));
        }

        $this->mfa->enrolled($user->id());
        $this->challenges->clear();
        $this->throttler->clearMfaUser($user->id());
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
    private function renderEnrollment(
        ServerRequestInterface $request,
        int $userId,
        bool $profile,
        bool $start = false,
        ?string $errorKey = null,
        int $status = HttpStatusCode::OK->value,
        ?string $errorMessage = null,
    ): ResponseInterface {
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
            'errorMessage' => $errorMessage,
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
     * Vraci stejnou navigaci pro HTML fallback i JSON auth transport
     */
    private function redirect(ServerRequestInterface $request, string $destination): ResponseInterface
    {
        if ($this->requests->wantsJson($request)) {
            return $this->responses->json([
                'success' => true,
                'redirect' => $destination,
            ]);
        }

        return $this->responses->redirect($destination);
    }

    /**
     * Vraci neplatny MFA kod do stejneho inline auth feedback kontraktu
     */
    private function failedChallenge(ServerRequestInterface $request): ResponseInterface
    {
        $errorKey = 'auth.mfa.errors.invalid';
        if (!$this->requests->wantsJson($request)) {
            return $this->render($request, 'admin-auth::mfa', ['errorKey' => $errorKey, 'errorMessage' => null], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }

        $message = $this->translator->get($errorKey);

        return $this->responses->json([
            'success' => false,
            'errors' => ['code' => $message],
            'message' => $message,
            'csrf' => [
                'name' => $this->csrf->fieldName(),
                'value' => $this->csrf->token(),
            ],
        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Vraci rate-limit odpoved s lokalizovanym casem do dalsiho MFA challenge pokusu
     */
    private function throttledChallenge(ServerRequestInterface $request, int $retryAfterSeconds): ResponseInterface
    {
        $errorKey = 'auth.mfa.errors.throttled';
        $message = $this->throttledMessage($errorKey, $retryAfterSeconds);
        if (!$this->requests->wantsJson($request)) {
            return $this->render($request, 'admin-auth::mfa', ['errorKey' => $errorKey, 'errorMessage' => $message], HttpStatusCode::TOO_MANY_REQUESTS->value)
                ->withHeader('Retry-After', (string) $retryAfterSeconds);
        }

        return $this->responses->json([
            'success' => false,
            'errors' => ['code' => $message],
            'message' => $message,
            'csrf' => [
                'name' => $this->csrf->fieldName(),
                'value' => $this->csrf->token(),
            ],
        ], HttpStatusCode::TOO_MANY_REQUESTS->value)->withHeader('Retry-After', (string) $retryAfterSeconds);
    }

    /**
     * Vraci rate-limit odpoved s lokalizovanym casem do dalsiho MFA enrollment pokusu
     */
    private function throttledEnrollment(ServerRequestInterface $request, int $userId, int $retryAfterSeconds): ResponseInterface
    {
        return $this->renderEnrollment(
            request: $request,
            userId: $userId,
            profile: false,
            errorKey: 'auth.mfa.errors.throttled',
            status: HttpStatusCode::TOO_MANY_REQUESTS->value,
            errorMessage: $this->throttledMessage('auth.mfa.errors.throttled', $retryAfterSeconds),
        )->withHeader('Retry-After', (string) $retryAfterSeconds);
    }

    /**
     * Vrati lokalizovany cas do odemknuti limitovaneho MFA flow
     */
    private function throttledMessage(string $key, int $retryAfterSeconds): string
    {
        if ($retryAfterSeconds < 60) {
            return $this->translator->get($key . '_seconds', ['seconds' => max(1, $retryAfterSeconds)]);
        }

        return $this->translator->get($key . '_minutes', ['minutes' => (int) ceil($retryAfterSeconds / 60)]);
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
