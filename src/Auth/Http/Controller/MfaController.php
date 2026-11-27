<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
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
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpProvisioningUriFactory;
use Lemonade\Framework\Security\Totp\TotpSecret;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles anonymous MFA challenges and the self-service local TOTP lifecycle
 */
final readonly class MfaController
{
    /**
     * Creates the MFA controller with its authentication and presentation dependencies
     */
    public function __construct(
        private AdminMfaConfiguration $configuration,
        private MfaAuthenticationService $mfa,
        private TotpCredentialService $credentials,
        private MfaLoginChallengeStore $challenges,
        private MfaRecentPrimaryAuthenticationStore $recentPrimary,
        private AuthenticationService $authentication,
        private AuthenticationThrottler $throttler,
        private AuditLogWriterInterface $audit,
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
     * Displays the TOTP challenge for a valid pending login
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
     * Verifies the challenge code before establishing the authenticated session
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

        if ($user === null) {
            $this->challenges->clear();

            return $this->redirect($request, $this->routing->path('/login'));
        }

        if (!$this->credentials->verifyAndConsume($challenge['userId'], $code)) {
            $limit = $this->throttler->failedMfa($request, $challenge['userId']);

            if (!$limit->allowed()) {
                $this->challenges->clear();
                $this->auditFailure('system.authentication.mfa_throttled', 'rate_limited');

                return $this->throttledChallenge($request, $limit->retryAfterSeconds());
            }

            $this->auditFailure('system.authentication.mfa_failed', 'invalid_code');

            return $this->failedChallenge($request);
        }

        $this->challenges->clear();
        $this->throttler->clearMfaUser($challenge['userId']);
        $this->authentication->login($user, true);

        return $this->redirect($request, $this->safeDestination($challenge['intendedPath']));
    }

    /**
     * Displays required enrollment during an unauthenticated login
     */
    public function loginEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();

        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'enroll') {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        return $this->renderEnrollment($request, $challenge['userId']);
    }

    /**
     * Starts or replaces required enrollment without establishing a user session
     */
    public function startLoginEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $challenge = $this->challenges->current();

        if (!$this->configuration->enabled() || $challenge === null || $challenge['kind'] !== 'enroll') {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        return $this->renderEnrollment(
            request: $request,
            userId: $challenge['userId'],
            start: true,
        );
    }

    /**
     * Confirms required enrollment and completes the authenticated login
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
                $this->auditFailure('system.authentication.mfa_throttled', 'rate_limited');

                return $this->throttledEnrollment($request, $challenge['userId'], $limit->retryAfterSeconds());
            }

            $this->auditFailure('system.authentication.mfa_failed', 'invalid_code');

            return $this->renderEnrollment(
                request: $request,
                userId: $challenge['userId'],
                errorKey: 'auth.mfa.errors.invalid',
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
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
     * Returns the self-service MFA modal for the current user
     */
    public function modal(): ResponseInterface
    {
        return $this->modalResponse();
    }

    /**
     * Verifies the current local password before a sensitive MFA change
     */
    public function reauthenticateModal(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->currentUser();
        $password = (string) (new RequestData($request))->post('password', '');

        if (
            $user === null
            || $this->authentication->authenticateLocal($user->email(), $password)->user()?->id() !== $user->id()
        ) {
            return $this->modalResponse(errorKey: 'auth.errors.invalid');
        }

        $this->recentPrimary->remember($user->id());

        return $this->modalResponse();
    }

    /**
     * Creates a TOTP secret after recent primary authentication
     */
    public function startModalEnrollment(): ResponseInterface
    {
        $user = $this->currentUser->currentUser();

        if ($user === null || !$this->recentPrimary->isRecentFor($user->id())) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.reauthenticate_required');
        }

        $secret = $this->credentials->beginEnrollment($user->id());

        return $this->modalResponse(
            secret: $secret->toBase32(),
            provisioningUri: $this->provisioningUri($user, $secret),
        );
    }

    /**
     * Confirms new MFA enrollment after recent primary authentication
     */
    public function confirmModalEnrollment(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->currentUser();

        if ($user === null || !$this->recentPrimary->isRecentFor($user->id())) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.reauthenticate_required');
        }

        $code = (string) (new RequestData($request))->post('code', '');

        if (!$this->credentials->confirmEnrollment($user->id(), $code)) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.invalid');
        }

        $this->mfa->enrolled($user->id());

        return $this->modalResponse(noticeKey: 'auth.mfa.enrolled');
    }

    /**
     * Revokes the current user's credential when optional MFA policy permits it
     */
    public function disableModal(): ResponseInterface
    {
        $user = $this->currentUser->currentUser();

        if (
            $user === null
            || $this->configuration->localRequirement !== 'optional'
            || !$this->recentPrimary->isRecentFor($user->id())
        ) {
            return $this->modalResponse(errorKey: 'auth.mfa.errors.disable_not_allowed');
        }

        $this->credentials->revoke($user->id());
        $this->mfa->disabled($user->id());

        return $this->modalResponse(noticeKey: 'auth.mfa.disabled');
    }

    /**
     * Renders enrollment and creates a secret only when explicitly requested
     */
    private function renderEnrollment(
        ServerRequestInterface $request,
        int $userId,
        bool $start = false,
        ?string $errorKey = null,
        HttpStatus $status = HttpStatus::OK,
        ?string $errorMessage = null,
    ): ResponseInterface {
        $secret = $start ? $this->credentials->beginEnrollment($userId) : null;
        $user = $this->mfa->eligibleUser($userId);

        if ($user === null) {
            return $this->responses->redirect($this->routing->path('/login'));
        }

        return $this->render(
            $request,
            'admin-auth::mfa-enroll',
            [
                'secret' => $secret?->toBase32(),
                'provisioningUri' => $secret === null
                    ? null
                    : $this->provisioningUri($user, $secret),
                'errorKey' => $errorKey,
                'errorMessage' => $errorMessage,
            ],
            $status,
        );
    }

    /**
     * Renders the authentication layout with MFA localization and branding
     *
     * @param array<string, mixed> $data
     */
    private function render(
        ServerRequestInterface $request,
        string $view,
        array $data,
        HttpStatus $status = HttpStatus::OK,
    ): ResponseInterface {
        return $this->pages->render($view, [
            ...$data,
            'title' => $this->branding->applicationName() . ' – ' . $this->translator->get('auth.mfa.title'),
            'locale' => $this->uiLocale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', '')),
            'clientTranslationVersion' => $this->translationVersion,
        ], $status->value);
    }

    /**
     * Returns equivalent navigation for HTML and JSON authentication transports
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
     * Returns an invalid MFA code through the inline authentication feedback contract
     */
    private function failedChallenge(ServerRequestInterface $request): ResponseInterface
    {
        $errorKey = 'auth.mfa.errors.invalid';

        if (!$this->requests->wantsJson($request)) {
            return $this->render(
                $request,
                'admin-auth::mfa',
                ['errorKey' => $errorKey, 'errorMessage' => null],
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $message = $this->translator->get($errorKey);

        return $this->challengeFailureResponse($message, HttpStatus::UNPROCESSABLE_ENTITY);
    }

    /**
     * Returns a rate-limit response for the next MFA challenge attempt
     */
    private function throttledChallenge(ServerRequestInterface $request, int $retryAfterSeconds): ResponseInterface
    {
        $errorKey = 'auth.mfa.errors.throttled';
        $message = $this->throttledMessage($errorKey, $retryAfterSeconds);

        if (!$this->requests->wantsJson($request)) {
            return $this->render(
                $request,
                'admin-auth::mfa',
                ['errorKey' => $errorKey, 'errorMessage' => $message],
                HttpStatus::TOO_MANY_REQUESTS,
            )
                ->withHeader('Retry-After', (string) $retryAfterSeconds);
        }

        return $this->challengeFailureResponse($message, HttpStatus::TOO_MANY_REQUESTS)
            ->withHeader('Retry-After', (string) $retryAfterSeconds);
    }

    /**
     * Returns a rate-limit response for the next MFA enrollment attempt
     */
    private function throttledEnrollment(ServerRequestInterface $request, int $userId, int $retryAfterSeconds): ResponseInterface
    {
        return $this->renderEnrollment(
            request: $request,
            userId: $userId,
            errorKey: 'auth.mfa.errors.throttled',
            status: HttpStatus::TOO_MANY_REQUESTS,
            errorMessage: $this->throttledMessage('auth.mfa.errors.throttled', $retryAfterSeconds),
        )->withHeader('Retry-After', (string) $retryAfterSeconds);
    }

    /**
     * Returns the localized delay before another MFA attempt is allowed
     */
    private function throttledMessage(string $key, int $retryAfterSeconds): string
    {
        if ($retryAfterSeconds < 60) {
            return $this->translator->get($key . '_seconds', ['seconds' => max(1, $retryAfterSeconds)]);
        }

        return $this->translator->get($key . '_minutes', ['minutes' => (int) ceil($retryAfterSeconds / 60)]);
    }

    /**
     * Creates the JSON response shared by failed MFA challenge attempts
     */
    private function challengeFailureResponse(string $message, HttpStatus $status): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'errors' => ['code' => $message],
            'message' => $message,
            'csrf' => [
                'name' => $this->csrf->fieldName(),
                'value' => $this->csrf->token(),
            ],
        ], $status->value);
    }

    /**
     * Creates a provisioning URI for the current TOTP enrollment
     */
    private function provisioningUri(AuthenticatedUser $user, TotpSecret $secret): string
    {
        return $this->provisioningUris->create(
            $this->configuration->issuer ?? '',
            $user->email(),
            $secret,
            new TotpConfiguration(),
        );
    }

    /**
     * Records an anonymous MFA failure without an identifier or TOTP value
     */
    private function auditFailure(string $eventCode, string $reason): void
    {
        $this->audit->record(
            new DomainEvent(
                code: $eventCode,
                moduleCode: 'system.authentication',
                entityType: 'authentication',
                entityKey: 'totp',
                payload: ['method' => 'totp', 'reason' => $reason],
            ),
            AuditActor::anonymous('admin-auth'),
        );
    }

    /**
     * Creates a JSON response containing the shared Admin modal
     */
    private function modalResponse(
        ?string $errorKey = null,
        ?string $noticeKey = null,
        ?string $secret = null,
        ?string $provisioningUri = null,
    ): ResponseInterface {
        $user = $this->currentUser->currentUser();

        if ($user === null) {
            return $this->responses->json(
                ['success' => false],
                HttpStatus::FORBIDDEN->value,
            );
        }

        $enabled = $this->configuration->enabled();

        return $this->responses->json([
            'success' => true,
            'modalHtml' => $this->views->content('admin-auth::mfa-modal', [
                'enabled' => $enabled,
                'active' => $enabled && $this->credentials->hasActiveCredential($user->id()),
                'recent' => $enabled && $this->recentPrimary->isRecentFor($user->id()),
                'canDisable' => $enabled && $this->configuration->localRequirement === 'optional',
                'errorKey' => $errorKey,
                'noticeKey' => $noticeKey,
                'secret' => $secret,
                'provisioningUri' => $provisioningUri,
            ]),
        ]);
    }

    /**
     * Returns a verified internal destination or the Admin root
     */
    private function safeDestination(?string $destination): string
    {
        return is_string($destination) && InternalAdminDestination::isSafe($destination, $this->routing)
            ? $destination
            : $this->routing->basePath;
    }
}
