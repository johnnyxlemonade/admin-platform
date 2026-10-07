<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Controller;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorRecordConflictException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Editor\Lock\EditorLockConflictMessageProviderInterface;
use Lemonade\Admin\Editor\Lock\EditorLockException;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenNames;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro module
 */
final class ModuleController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminModuleRouteResolver $routes,
        private readonly AdminModuleAccessPolicy $moduleAccess,
        private readonly ModuleManager $modules,
        private readonly ModulePageRegistry $pageRegistry,
        private readonly EditorDispatcher $editors,
        private readonly EditorLockManager $locks,
        private readonly AuthorizationService $authorization,
        private readonly AdminUiLocale $locale,
        private readonly TranslatorInterface $translator,
        private readonly AdminResponseFactory $responses,
        private readonly AdminPageRenderer $pages,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Vykresluje nebo vraci data pozadovane administracni stranky
     */
    public function index(string $module, ServerRequestInterface $request): ResponseInterface
    {
        [$code, $locale, $allowed] = $this->module($module, $request);
        if ($code === null) {
            return $this->responses->notFound($request);
        }
        if (!$allowed) {
            return $this->responses->authorizationDenied($request);
        }
        if (!$this->pageRegistry->hasIndex($code)) {
            return $this->responses->notFound($request);
        }
        $provider = $this->pageRegistry->index($code);
        if (!$this->authorization->hasPermission($provider->indexPermission())) {
            return $this->responses->authorizationDenied($request);
        }
        return $this->render($provider->index($locale));
    }

    /**
     * Nacita a vykresluje editor pozadovaneho zaznamu
     */
    public function edit(string $module, int $id, ServerRequestInterface $request): ResponseInterface
    {
        return $this->editorPage($module, $id, $request);
    }

    /**
     * Vytvari novy zaznam z dat pozadavku
     */
    public function create(string $module, ServerRequestInterface $request): ResponseInterface
    {
        return $this->createPage($module, $request);
    }

    /**
     * Uklada novy zaznam z dat pozadavku
     */
    public function store(string $module, ServerRequestInterface $request): ResponseInterface
    {
        [$code, , $allowed] = $this->module($module, $request);
        if ($code === null) {
            return $this->responses->notFound($request);
        }
        if (!$allowed) {
            return $this->responses->authorizationDenied($request);
        }
        try {
            $outcome = $this->editors->create($code, $this->editorPayload($request));
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatusCode::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        } catch (EditorValidationException $exception) {
            return $this->createPage(
                $module,
                $request,
                ['_form' => $this->translator->get($exception->messageKey())],
                $this->safeInput($request),
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        }
        if (!$outcome->isSaved()) {
            return $this->createPage(
                $module,
                $request,
                $outcome->validation()->errors(),
                $outcome->input(),
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        }
        $id = (int) ($outcome->result()->data()['id'] ?? 0);
        $editUrl = $outcome->result()->data()['editUrl'] ?? null;

        return $this->responses->redirect($id > 0 && $this->pageRegistry->hasEditor($code)
            ? (is_string($editUrl) ? $editUrl : $this->urls->route($this->routeName($module, 'edit'), ['module' => $module, 'id' => $id]))
            : $this->urls->route($this->routeName($module, 'index'), ['module' => $module]));
    }

    /**
     * Uklada zmeny existujiciho zaznamu
     */
    public function update(string $module, int $id, ServerRequestInterface $request): ResponseInterface
    {
        [$code, , $allowed] = $this->module($module, $request);
        if ($code === null) {
            return $this->responses->notFound($request);
        }
        if (!$allowed) {
            return $this->responses->authorizationDenied($request);
        }
        try {
            $outcome = $this->editors->update($code, $id, $this->editorPayload($request));
        } catch (EditorValidationException $exception) {
            return $this->editorPage(
                $module,
                $id,
                $request,
                ['_form' => $this->translator->get($exception->messageKey())],
                $this->safeInput($request),
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        } catch (EditorEntityNotFoundException) {
            return $this->responses->recordNotFound($request, $this->moduleIndexDestination($module));
        } catch (EditorLockException) {
            return $this->responses->recordLocked($request, $this->moduleIndexDestination($module));
        } catch (EditorRecordConflictException) {
            return $this->responses->recordConflict($request, $this->urls->route($this->routeName($module, 'edit'), ['module' => $module, 'id' => $id]));
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatusCode::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }
        if (!$outcome->isSaved()) {
            return $this->editorPage(
                $module,
                $id,
                $request,
                $outcome->validation()->errors(),
                $outcome->input(),
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        }
        $this->locks->release($code, (string) $id);
        $editUrl = $outcome->result()->data()['editUrl'] ?? null;

        return $this->responses->redirect(is_string($editUrl)
            ? $editUrl
            : $this->urls->route($this->routeName($module, 'edit'), ['module' => $module, 'id' => $id]));
    }

    /**
     * Zpracovava krok editorpage v HTTP toku administrace
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function editorPage(string $module, int $id, ServerRequestInterface $request, array $errors = [], array $input = [], ?int $status = null): ResponseInterface
    {
        $status ??= HttpStatusCode::OK->value;
        [$code, $locale, $allowed] = $this->module($module, $request);
        if ($code === null || !$this->pageRegistry->hasEditor($code)) {
            return $this->responses->notFound($request);
        }
        if (!$allowed) {
            return $this->responses->authorizationDenied($request);
        }
        $pageProvider = $this->pageRegistry->editor($code);
        try {
            $editor = $this->editors->updateData($code, $id);
        } catch (EditorEntityNotFoundException) {
            return $this->responses->recordNotFound($request, $this->moduleIndexDestination($module));
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatusCode::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }
        $acquisition = $this->locks->acquire($code, (string) $id);
        if (!$acquisition->acquired) {
            if ($this->editors->canBypassForeignLock($code, $id)) {
                try {
                    $page = $pageProvider->editor($editor, $errors, $input, $locale, $request->getQueryParams());
                } catch (EditorEntityNotFoundException) {
                    return $this->responses->recordNotFound($request, $this->moduleIndexDestination($module));
                }

                return $this->render($page);
            }
            $message = $acquisition->lockedBy !== null && $pageProvider instanceof EditorLockConflictMessageProviderInterface
                ? $pageProvider->editorOpenLockConflictMessage($id, $acquisition->lockedBy)
                : null;

            return $this->responses->recordLocked($request, $this->moduleIndexDestination($module), $acquisition->lockedBy, $message);
        }

        try {
            $page = $pageProvider->editor($editor, $errors, $input, $locale, $request->getQueryParams());
        } catch (EditorEntityNotFoundException) {
            return $this->responses->recordNotFound($request, $this->moduleIndexDestination($module));
        }

        return $this->render($page, $status);
    }

    /**
     * Zpracovava krok createpage v HTTP toku administrace
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function createPage(string $module, ServerRequestInterface $request, array $errors = [], array $input = [], ?int $status = null): ResponseInterface
    {
        $status ??= HttpStatusCode::OK->value;
        [$code, $locale, $allowed] = $this->module($module, $request);
        if ($code === null || !$this->pageRegistry->hasEditor($code)) {
            return $this->responses->notFound($request);
        }
        if (!$allowed) {
            return $this->responses->authorizationDenied($request);
        }
        try {
            $editor = $this->editors->createData($code);
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatusCode::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }

        try {
            $page = $this->pageRegistry->editor($code)->create($editor, $errors, $input, $locale, $request->getQueryParams());
        } catch (EditorEntityNotFoundException) {
            return $this->responses->notFound($request);
        }

        return $this->render($page, $status);
    }

    /**
     * Zpracovava krok module v HTTP toku administrace
     * @return array{0:string|null,1:string,2:bool}
     */
    private function module(string $segment, ServerRequestInterface $request): array
    {
        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));
        try {
            $definition = $this->routes->resolve($segment);
        } catch (\RuntimeException) {
            return [null, $locale, false];
        }

        return $this->modules->enabled($definition->code())
            ? [$definition->code(), $locale, $this->moduleAccess->canAccess($definition)]
            : [null, $locale, false];
    }

    /**
     * Zpracovava krok moduleindexdestination v HTTP toku administrace
     */
    private function moduleIndexDestination(string $segment): string
    {
        try {
            $metadata = $this->routes->metadata($segment);
            if (!$this->routes->isCmsSegment($segment)) {
                return $this->urls->route($metadata->destinationRoute(), $metadata->destinationParameters());
            }

            return $this->urls->route(
                $this->routes->managementRouteName($segment, 'index'),
                ['module' => $segment],
            );
        } catch (\RuntimeException) {
            return $this->urls->route('admin.dashboard');
        }
    }

    /**
     * Urci route transportu podle ownershipu pozadovaneho modulu
     */
    private function routeName(string $segment, string $action): string
    {
        return $this->routes->managementRouteName($segment, $action);
    }

    /**
     * Zpracovava krok render v HTTP toku administrace
     */
    private function render(ModulePage $page, ?int $status = null): ResponseInterface
    {
        $status ??= HttpStatusCode::OK->value;
        return $this->pages->render($page->view(), ['title' => $page->title(), ...$page->data()], $status);
    }

    /**
     * Zpracovava krok safeinput v HTTP toku administrace
     * @return array<string, mixed>
     */
    private function safeInput(ServerRequestInterface $request): array
    {
        $input = $this->editorPayload($request);
        unset($input['local_password']);
        return $input;
    }

    /**
     * Vrati flat editorova data z canonical action payloadu
     * @return array<string, mixed>
     */
    private function editorPayload(ServerRequestInterface $request): array
    {
        $payload = (new RequestData($request))->postAll();
        unset($payload[CsrfTokenNames::FORM_FIELD]);

        $editorPayload = $payload['payload'] ?? $payload;

        return is_array($editorPayload) ? $editorPayload : [];
    }
}
