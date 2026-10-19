<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthenticationThrottler;
use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\MfaLoginChallengeStore;
use Lemonade\Admin\Auth\Oidc\OidcAuthenticationService;
use Lemonade\Admin\Auth\Oidc\OidcAuthorizationCallback;
use Lemonade\Admin\Auth\Oidc\OidcLogoutService;
use Lemonade\Admin\Auth\Oidc\OidcProtocolException;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
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
use Lemonade\Framework\Session\Contract\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro login
 */
final readonly class LoginController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private AuthenticationService $authentication,
        private AuditLogWriterInterface $audit,
        private AuthenticationThrottler $throttler,
        private MfaAuthenticationService $mfa,
        private MfaLoginChallengeStore $mfaChallenges,
        private AdminUiLocale $uiLocale,
        private SessionInterface $session,
        private CsrfViewHelper $csrf,
        private TranslatorInterface $translator,
        private OidcAuthenticationService $oidc,
        private OidcLogoutService $oidcLogout,
        private OidcProviderConfiguration $oidcConfiguration,
        private ClientTranslationVersion $translationVersion,
        private AdminBranding $branding,
        private AdminRoutingConfiguration $routing,
        private AuthPageRenderer $pages,
        private Responses $responses,
        private HttpRequestInspector $requests,
        private Psr17Factory $psr17,
    ) {}

    /**
     * Zpracovava krok form v HTTP toku administrace
     */
    public function form(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $this->uiLocale->activate(
            (string) $requestData->query('locale', ''),
            (string) $requestData->cookie('lemonade_locale', ''),
        );

        return $this->loginPage(
            request: $request,
            errorKey: $requestData->query('session_expired', '') === '1' ? 'auth.errors.session_expired' : null,
        );
    }

    /**
     * Overuje prihlasovaci udaje a zalozi administracni relaci
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $this->uiLocale->activate(null, (string) $requestData->cookie('lemonade_locale', ''));

        $email = (string) $requestData->post('email', '');
        $password = (string) $requestData->post('password', '');
        if (trim($email) === '' || $password === '') {
            return $this->failedLogin($request, 'auth.errors.required', $email, HttpStatusCode::UNPROCESSABLE_ENTITY->value, [
                'login' => $this->errorText('auth.errors.required'),
                'password' => $password === '' ? $this->errorText('auth.errors.required') : '',
            ]);
        }

        $limit = $this->throttler->loginStatus($request, $email);
        if (!$limit->allowed()) {
            return $this->throttledLogin($request, $email, $limit->retryAfterSeconds());
        }

        $attempt = $this->authentication->authenticateLocal($email, $password);
        $user = $attempt->user();
        if ($user === null) {
            $normalizedEmail = LocalAuthenticationProvider::normalizeEmail($email);
            $limit = $this->throttler->failedLogin($request, $email);
            if (!$limit->allowed()) {
                $this->auditFailure('system.authentication.login_throttled', 'rate_limited', $normalizedEmail);
                return $this->throttledLogin($request, $email, $limit->retryAfterSeconds());
            }

            $this->auditFailure('system.authentication.login_failed', 'invalid_credentials', $normalizedEmail);

            $errorKey = $attempt->isInactive() ? 'auth.errors.disabled' : 'auth.errors.invalid';

            return $this->failedLogin(
                $request,
                $errorKey,
                $email,
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
                ['login' => $this->errorText($errorKey)],
            );
        }

        $this->throttler->clearLoginIdentity($email);

        $redirect = AdminAuthenticationMiddleware::pullIntendedPath($this->session, $this->routing);
        $requirement = $this->mfa->loginRequirement($user);
        if ($requirement !== 'login') {
            $this->authentication->beginMfaChallenge();
            $this->mfaChallenges->begin($user->id(), $requirement === 'enroll' ? 'enroll' : 'verify', $redirect);

            $mfaPath = $this->routing->path($requirement === 'enroll' ? '/login/mfa/enroll' : '/login/mfa');
            if ($this->requests->wantsJson($request)) {
                return $this->responses->json([
                    'success' => true,
                    'redirect' => $mfaPath,
                ]);
            }

            return $this->responses->redirect($mfaPath);
        }

        $this->authentication->login($user);

        if ($this->requests->wantsJson($request)) {
            return $this->responses->json([
                'success' => true,
                'redirect' => $redirect,
            ]);
        }

        return $this->responses->redirect($redirect);
    }

    /**
     * Ukoncuje aktualni administracni relaci
     */
    public function logout(): ResponseInterface
    {
        $providerLogoutUrl = $this->authentication->oidcLogoutContext() !== null
            ? $this->oidcLogout->providerLogoutUrl($this->authentication->oidcLogoutIdToken())
            : null;
        $this->authentication->logout();

        return $this->responses->redirect($providerLogoutUrl ?? $this->routing->path('/login'));
    }

    /**
     * Zpracovava krok keycloak v HTTP toku administrace
     */
    public function keycloak(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->responses->redirect($this->oidc->begin(AdminAuthenticationMiddleware::pullIntendedPath($this->session, $this->routing)));
        } catch (OidcProtocolException) {
            return $this->loginPage($request, 'auth.errors.invalid', '', HttpStatusCode::FORBIDDEN->value);
        }
    }

    /**
     * Zpracovava krok keycloakcallback v HTTP toku administrace
     */
    public function keycloakCallback(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $destination = $this->oidc->complete(OidcAuthorizationCallback::fromQuery((new RequestData($request))->queryAll()));
            if ($destination === OidcAuthenticationService::SESSION_UNLOCK_CALLBACK) {
                return $this->popupResult(true);
            }

            return $this->responses->redirect($destination);
        } catch (OidcProtocolException) {
            if ($this->authentication->sessionState()->value === 'locked' && $this->authentication->oidcLogoutContext() !== null) {
                return $this->popupResult(false);
            }

            return $this->loginPage($request, 'auth.errors.invalid', '', HttpStatusCode::FORBIDDEN->value);
        }
    }

    /**
     * Vrati minimalni same-origin odpoved pro OIDC unlock popup
     */
    private function popupResult(bool $success): ResponseInterface
    {
        $payload = json_encode(['type' => 'lemonade:session:oidc-unlock', 'success' => $success], JSON_THROW_ON_ERROR);
        $body = '<!doctype html><script>window.opener?.postMessage(' . $payload . ',window.location.origin);window.close();</script>';

        return $this->psr17->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->psr17->createStream($body));
    }

    /**
     * Zpracovava krok loginpage v HTTP toku administrace
     */
    private function loginPage(
        ServerRequestInterface $request,
        ?string $errorKey = null,
        string $email = '',
        ?int $status = null,
        ?string $errorMessage = null,
    ): ResponseInterface {
        $status ??= HttpStatusCode::OK->value;
        return $this->pages->render('admin-auth::login', [
            'title' => $this->branding->applicationName . ' – ' . $this->translator->get('auth.login.page_title'),
            'errorKey' => $errorKey,
            'errorMessage' => $errorMessage,
            'email' => $email,
            'locale' => $this->uiLocale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', '')),
            'oidcEnabled' => $this->oidcConfiguration->isEnabled(),
            'clientTranslationVersion' => $this->translationVersion,
        ], $status);
    }

    /**
     * Zpracovava krok failedlogin v HTTP toku administrace
     *
     * @param array<string, string> $errors
     */
    private function failedLogin(ServerRequestInterface $request, string $errorKey, string $email, int $status, array $errors, ?string $message = null): ResponseInterface
    {
        if (!$this->requests->wantsJson($request)) {
            return $this->loginPage($request, $errorKey, $email, $status, $message);
        }

        return $this->responses->json([
            'success' => false,
            'errors' => array_filter($errors, static fn(string $message): bool => $message !== ''),
            'message' => $message ?? $this->errorText($errorKey),
            'csrf' => [
                'name' => $this->csrf->fieldName(),
                'value' => $this->csrf->token(),
            ],
        ], $status);
    }

    /**
     * Vraci rate-limit odpoved s lokalizovanym casem do dalsiho login pokusu
     */
    private function throttledLogin(ServerRequestInterface $request, string $email, int $retryAfterSeconds): ResponseInterface
    {
        $errorKey = 'auth.errors.throttled';
        $message = $this->throttledMessage($errorKey, $retryAfterSeconds);
        $response = $this->failedLogin(
            request: $request,
            errorKey: $errorKey,
            email: $email,
            status: HttpStatusCode::TOO_MANY_REQUESTS->value,
            errors: ['login' => $message],
            message: $message,
        );

        return $response->withHeader('Retry-After', (string) $retryAfterSeconds);
    }

    /**
     * Zpracovava krok errortext v HTTP toku administrace
     */
    private function errorText(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * Vrati lokalizovany cas do odemknuti limitovaneho auth flow
     */
    private function throttledMessage(string $key, int $retryAfterSeconds): string
    {
        if ($retryAfterSeconds < 60) {
            return $this->translator->get($key . '_seconds', ['seconds' => max(1, $retryAfterSeconds)]);
        }

        return $this->translator->get($key . '_minutes', ['minutes' => (int) ceil($retryAfterSeconds / 60)]);
    }

    /**
     * Zapise anonymni local login failure pouze s bezpecne normalizovanym e-mailem
     */
    private function auditFailure(string $eventCode, string $reason, string $email): void
    {
        $payload = ['method' => 'local', 'reason' => $reason];
        $email = LocalAuthenticationProvider::normalizeEmail($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $payload['email'] = $email;
        }

        $this->audit->record(
            new DomainEvent($eventCode, 'system.authentication', 'authentication', 'local', $payload),
            AuditActor::anonymous('admin-auth'),
        );
    }
}
