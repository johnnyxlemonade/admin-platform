<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Auth\AdminSessionState;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthenticationThrottler;
use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\Oidc\OidcAuthenticationService;
use Lemonade\Admin\Auth\Oidc\OidcProtocolException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje status a lokalni odemknuti uzamcene Admin relace
 */
final readonly class SessionLockController
{
    public function __construct(
        private AuthenticationService $authentication,
        private CurrentUserProvider $currentUser,
        private AuthenticationThrottler $throttler,
        private Responses $responses,
        private Psr17Factory $psr17,
        private OidcAuthenticationService $oidc,
        private AuthPageRenderer $pages,
        private AdminUiLocale $uiLocale,
        private ClientTranslationVersion $translationVersion,
        private AdminBranding $branding,
        private AdminRoutingConfiguration $routing,
    ) {}

    public function status(): ResponseInterface
    {
        if ($this->authentication->sessionState() === AdminSessionState::LOCKED) {
            return $this->responses->json([
                'error' => ['code' => AdminErrorCode::SESSION_LOCKED->value],
                'unlock' => $this->authentication->oidcLogoutContext() === null ? 'local' : 'oidc',
            ], 423);
        }

        return $this->psr17->createResponse(HttpStatus::NO_CONTENT->value);
    }

    /**
     * Vykresli samostatnou obrazovku pro locked document request
     */
    public function screen(ServerRequestInterface $request): ResponseInterface
    {
        $return = (string) (new RequestData($request))->query('return', '');
        $destination = \Lemonade\Admin\Auth\InternalAdminDestination::isSafe($return, $this->routing) ? $return : $this->routing->basePath;
        if ($this->authentication->sessionState() === AdminSessionState::ACTIVE) {
            return $this->responses->redirect($destination);
        }

        return $this->pages->render('admin-auth::session-lock', [
            'title' => $this->branding->applicationName . ' – Session locked',
            'locale' => $this->uiLocale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', '')),
            'clientTranslationVersion' => $this->translationVersion,
            'returnUrl' => $destination,
            'oidc' => $this->authentication->oidcLogoutContext() !== null,
        ]);
    }

    public function unlock(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->authentication->oidcLogoutContext() !== null) {
            return $this->responses->json(['error' => ['code' => 'session_unlock_provider_required']], HttpStatus::UNPROCESSABLE_ENTITY->value);
        }

        $user = $this->currentUser->currentUser();
        if ($user === null || $this->authentication->sessionState() !== AdminSessionState::LOCKED) {
            return $this->responses->json(['error' => ['code' => AdminErrorCode::SESSION_UNLOCK_INVALID->value]], HttpStatus::UNPROCESSABLE_ENTITY->value);
        }

        $limit = $this->throttler->loginStatus($request, $user->email());
        if (!$limit->allowed()) {
            return $this->responses->json(['error' => ['code' => 'throttled']], HttpStatus::TOO_MANY_REQUESTS->value)
                ->withHeader('Retry-After', (string) $limit->retryAfterSeconds());
        }

        $password = (string) (new RequestData($request))->input('password', '');
        if ($this->authentication->authenticateLocal($user->email(), $password)->user()?->id() !== $user->id() || !$this->authentication->unlock()) {
            $this->throttler->failedLogin($request, $user->email());

            return $this->responses->json(['error' => ['code' => AdminErrorCode::SESSION_UNLOCK_INVALID->value]], HttpStatus::UNPROCESSABLE_ENTITY->value);
        }

        $this->throttler->clearLoginIdentity($user->email());

        return $this->responses->json(['success' => true]);
    }

    /**
     * Presmeruje popup do OIDC reautentizace uzamcene relace
     */
    public function oidcUnlock(): ResponseInterface
    {
        try {
            return $this->responses->redirect($this->oidc->beginSessionUnlock());
        } catch (OidcProtocolException) {
            return $this->responses->json(['error' => ['code' => AdminErrorCode::SESSION_UNLOCK_INVALID->value]], HttpStatus::UNPROCESSABLE_ENTITY->value);
        }
    }
}
