<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Http\Controller;

use Lemonade\Admin\Action\Http\Controller\ModuleActionController;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorRecordConflictException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Editor\Lock\EditorLockException;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Admin\System\Users\UsersModulePageProvider;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenNames;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje canonical self-service profil nad existujicim Users editorem
 */
final readonly class UsersProfileController
{
    /**
     * Nastavuje session identity, shared editorovy transport a Users presentation
     */
    public function __construct(
        private CurrentPrincipalProviderInterface $currentUser,
        private EditorDispatcher $editors,
        private EditorLockManager $locks,
        private UsersModulePageProvider $pages,
        private ModuleActionController $actions,
        private ModuleController $modules,
        private AdminUiLocale $locale,
        private TranslatorInterface $translator,
        private AdminResponseFactory $responses,
        private AdminPageRenderer $renderer,
        private UrlGenerator $urls,
    ) {}

    /**
     * Vykresluje profil aktualniho uzivatele bez prijeti ciloveho ID z requestu
     */
    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        return $this->profilePage($request);
    }

    /**
     * Uklada profil aktualniho uzivatele pres standardni Users editorovy workflow
     */
    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->currentUserId($request);
        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        try {
            $outcome = $this->editors->update('system.users', $userId, $this->editorPayload($request));
        } catch (EditorValidationException $exception) {
            return $this->profilePage(
                request: $request,
                errors: ['_form' => $this->translator->get($exception->messageKey())],
                input: $this->safeInput($request),
                status: HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        } catch (EditorEntityNotFoundException) {
            return $this->responses->notFound($request);
        } catch (EditorLockException) {
            return $this->responses->recordLocked($request, $this->profileUrl());
        } catch (EditorRecordConflictException) {
            return $this->responses->recordConflict($request, $this->profileUrl());
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatusCode::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }

        if (!$outcome->isSaved()) {
            return $this->profilePage(
                request: $request,
                errors: $outcome->validation()->errors(),
                input: $outcome->input(),
                status: HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        }

        $this->locks->release('system.users', (string) $userId);

        return $this->responses->redirect($this->profileUrl());
    }

    /**
     * Predava AJAX save do stavajiciho Users action transportu s ID pouze ze session
     */
    public function ajaxSave(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->currentUserId($request);
        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        return $this->actions->entity('users', $userId, $request);
    }

    /**
     * Presmeruje vlastni legacy edit URL na canonical profil a ostatni editace deleguje management controlleru
     */
    public function edit(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $currentUserId = $this->currentUserId($request);
        if ($currentUserId !== null && $id === $currentUserId) {
            return $this->responses->redirect($this->profileUrl());
        }

        return $this->modules->edit('users', $id, $request);
    }

    /**
     * Vykresluje profile editor s existujicim lock a self-service access contractem
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function profilePage(ServerRequestInterface $request, array $errors = [], array $input = [], ?int $status = null): ResponseInterface
    {
        $userId = $this->currentUserId($request);
        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        $status ??= HttpStatusCode::OK->value;
        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));
        try {
            $editor = $this->editors->updateData('system.users', $userId);
        } catch (EditorEntityNotFoundException) {
            return $this->responses->notFound($request);
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatusCode::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }

        $this->locks->acquire('system.users', (string) $userId);

        return $this->renderProfile($editor, $errors, $input, $locale, $status);
    }

    /**
     * Renderuje Users profile page pres existujici module page provider
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function renderProfile(EditorLoaded $editor, array $errors, array $input, string $locale, int $status): ResponseInterface
    {
        $page = $this->pages->profile($editor, $errors, $input, $locale);

        return $this->renderer->render($page->view(), ['title' => $page->title(), ...$page->data()], $status);
    }

    /**
     * Vraci aktivni Users ID vyhradne z autentizovane session identity
     */
    private function currentUserId(ServerRequestInterface $request): ?int
    {
        unset($request);

        return $this->currentUser->currentUser()?->id();
    }

    /**
     * Vraci bezpecny vstup pro opetovne vykresleni HTML formulare
     *
     * @return array<string, mixed>
     */
    private function safeInput(ServerRequestInterface $request): array
    {
        $input = $this->editorPayload($request);
        unset($input['local_password']);

        return $input;
    }

    /**
     * Normalizuje fallback form payload na stejny tvar jako shared editor transport
     *
     * @return array<string, mixed>
     */
    private function editorPayload(ServerRequestInterface $request): array
    {
        $payload = (new RequestData($request))->postAll();
        unset($payload[CsrfTokenNames::FORM_FIELD]);
        $editorPayload = $payload['payload'] ?? $payload;

        return is_array($editorPayload) ? $editorPayload : [];
    }

    /**
     * Generuje canonical profilovou adresu pres named route
     */
    private function profileUrl(): string
    {
        return $this->urls->route('admin.system.users.profile');
    }
}
