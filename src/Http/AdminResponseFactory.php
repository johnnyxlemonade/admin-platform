<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http;

use Lemonade\Admin\Editor\Lock\EditorLockConflictMessage;
use Lemonade\Admin\Editor\Lock\EditorLockOwner;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Vytvari HTTP odpovedi pro administracni scenare
 */
final class AdminResponseFactory
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminAuthorizationResponseHandler $authorization,
        private readonly AdminPageRenderer $pages,
        private readonly AdminUiLocale $locale,
        private readonly TranslatorInterface $translator,
        private readonly FlashBagInterface $flash,
        private readonly UrlGenerator $urls,
        private readonly Responses $responses,
    ) {}

    /**
     * Vytvari presmerovani na predanou adresu
     */
    public function redirect(string $destination, int $status = 302): ResponseInterface
    {
        return $this->responses->redirect($destination, $status);
    }

    /**
     * Vraci odpoved pro zamitnutou autorizaci
     */
    public function authorizationDenied(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->authorization->wantsJson($request)) {
            return $this->responses->json($this->authorization->forbiddenPayload(), HttpStatusCode::FORBIDDEN->value);
        }

        $this->authorization->flashForbidden($this->flash);

        return $this->responses->redirect($this->urls->route('admin.dashboard'));
    }

    /**
     * Vraci odpoved pro nenalezeny zaznam
     */
    public function recordNotFound(ServerRequestInterface $request, string $destination): ResponseInterface
    {
        if ($this->authorization->wantsJson($request)) {
            return $this->responses->json($this->authorization->recordNotFoundPayload(), HttpStatusCode::NOT_FOUND->value);
        }

        $this->authorization->flashRecordNotFound($this->flash);

        return $this->responses->redirect($destination);
    }

    /**
     * Vraci odpoved pro zaznam zamceny jinym uzivatelem
     */
    public function recordLocked(ServerRequestInterface $request, string $destination, ?EditorLockOwner $owner = null, ?EditorLockConflictMessage $message = null): ResponseInterface
    {
        if ($this->authorization->wantsJson($request)) {
            return $this->responses->json($this->authorization->recordLockedPayload($owner, $message), HttpStatusCode::CONFLICT->value);
        }

        $this->authorization->flashRecordLocked($this->flash, $owner, $message);

        return $this->responses->redirect($destination);
    }

    /**
     * Vraci odpoved pro konflikt soubezne zmeny
     */
    public function recordConflict(ServerRequestInterface $request, string $destination): ResponseInterface
    {
        if ($this->authorization->wantsJson($request)) {
            return $this->responses->json($this->authorization->recordConflictPayload(), HttpStatusCode::CONFLICT->value);
        }

        $this->authorization->flashRecordConflict($this->flash);

        return $this->responses->redirect($destination);
    }

    /**
     * Vraci odpoved pro nenalezeny administracni cil
     */
    public function notFound(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->authorization->wantsJson($request) || strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return $this->responses->json([
                'success' => false,
                'error' => [
                    'code' => AdminErrorCode::NOT_FOUND->value,
                    'messageKey' => 'admin.errors.not_found.title',
                ],
                'messageKey' => 'admin.errors.not_found.title',
            ], HttpStatusCode::NOT_FOUND->value);
        }

        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));

        return $this->pages->render('admin::errors.not-found', [
            'title' => $this->translator->get('admin.errors.not_found.title'),
            'locale' => $locale,
        ], HttpStatusCode::NOT_FOUND->value);
    }
}
