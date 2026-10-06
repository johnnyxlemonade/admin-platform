<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Auth\Oidc\OidcAuthenticationService;
use Lemonade\Admin\Auth\Oidc\OidcAuthorizationCallback;
use Lemonade\Admin\Auth\Oidc\OidcLogoutService;
use Lemonade\Admin\Auth\Oidc\OidcProtocolException;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro login
 */
final class LoginController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly AdminUiLocale $uiLocale,
        private readonly SessionInterface $session,
        private readonly CsrfViewHelper $csrf,
        private readonly TranslatorInterface $translator,
        private readonly OidcAuthenticationService $oidc,
        private readonly OidcLogoutService $oidcLogout,
        private readonly OidcProviderConfiguration $oidcConfiguration,
        private readonly ClientTranslationVersion $translationVersion,
        private readonly AdminBranding $branding,
        private readonly AdminRoutingConfiguration $routing,
        private readonly AuthPageRenderer $pages,
        private readonly Responses $responses,
        private readonly HttpRequestInspector $requests,
    ) {}

    /**
     * Zpracovava krok form v HTTP toku administrace
     */
    public function form(ServerRequestInterface $request): ResponseInterface
    {
        $this->uiLocale->activate(
            (string) (new RequestData($request))->query('locale', ''),
            (string) (new RequestData($request))->cookie('lemonade_locale', ''),
        );

        return $this->loginPage($request);
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

        $attempt = $this->authentication->authenticateLocal($email, $password);
        $user = $attempt->user();
        if ($user === null) {
            $errorKey = $attempt->isInactive() ? 'auth.errors.disabled' : 'auth.errors.invalid';

            return $this->failedLogin(
                $request,
                $errorKey,
                $email,
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
                ['login' => $this->errorText($errorKey)],
            );
        }

        $this->authentication->login($user);

        $redirect = AdminAuthenticationMiddleware::pullIntendedPath($this->session, $this->routing);

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
            return $this->responses->redirect($this->oidc->complete(OidcAuthorizationCallback::fromQuery((new RequestData($request))->queryAll())));
        } catch (OidcProtocolException) {
            return $this->loginPage($request, 'auth.errors.invalid', '', HttpStatusCode::FORBIDDEN->value);
        }
    }

    /**
     * Zpracovava krok loginpage v HTTP toku administrace
     */
    private function loginPage(ServerRequestInterface $request, ?string $errorKey = null, string $email = '', ?int $status = null): ResponseInterface
    {
        $status ??= HttpStatusCode::OK->value;
        return $this->pages->render('admin-auth::login', [
            'title' => $this->branding->applicationName . ' – ' . $this->translator->get('auth.login.page_title'),
            'errorKey' => $errorKey,
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
    private function failedLogin(ServerRequestInterface $request, string $errorKey, string $email, int $status, array $errors): ResponseInterface
    {
        if (!$this->requests->wantsJson($request)) {
            return $this->loginPage($request, $errorKey, $email, $status);
        }

        return $this->responses->json([
            'success' => false,
            'errors' => array_filter($errors, static fn(string $message): bool => $message !== ''),
            'message' => $this->errorText($errorKey),
            'csrf' => [
                'name' => $this->csrf->fieldName(),
                'value' => $this->csrf->token(),
            ],
        ], $status);
    }

    /**
     * Zpracovava krok errortext v HTTP toku administrace
     */
    private function errorText(string $key): string
    {
        return $this->translator->get($key);
    }
}
