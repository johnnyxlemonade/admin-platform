<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Http\Controller;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Lock\EditorModalLoader;
use Lemonade\Admin\Editor\Lock\EditorModalLockedException;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje sdileny JSON transport modalnich editoru admin modulu
 */
final class EditorModalController
{
    /**
     * Nastavuje capability lookup, editorovy transport a JSON rendering
     */
    public function __construct(
        private readonly AdminModuleRouteResolver $routes,
        private readonly AdminModuleAccessPolicy $moduleAccess,
        private readonly ModuleManager $modules,
        private readonly ModulePageRegistry $pages,
        private readonly ModuleActionRegistry $actions,
        private readonly EditorDispatcher $editors,
        private readonly EditorModalLoader $modalLoader,
        private readonly AdminAuthorizationResponseHandler $authorizationResponses,
        private readonly AdminUiLocale $locale,
        private readonly ViewRendererInterface $views,
        private readonly Responses $responses,
    ) {}

    /**
     * Nacte create data a renderuje modalni editor pro pozadovany modul
     */
    public function create(string $module, ServerRequestInterface $request): ResponseInterface
    {
        [$moduleCode, $locale, $forbidden] = $this->modalCapability($module, $request);
        if ($moduleCode === null) {
            return $forbidden ? $this->forbidden() : $this->notFound();
        }
        if (!$this->actions->has($moduleCode, 'create')) {
            return $this->notFound();
        }

        try {
            $editor = $this->editors->createData($moduleCode);
        } catch (EditorCapabilityException $exception) {
            return $this->capabilityResponse($exception);
        }

        return $this->modalResponse($this->pages->modalEditor($moduleCode)->modalCreate($editor, $locale));
    }

    /**
     * Nacte zamykany zaznam a renderuje modalni editor pro pozadovany modul
     */
    public function edit(string $module, int $id, ServerRequestInterface $request): ResponseInterface
    {
        [$moduleCode, $locale, $forbidden] = $this->modalCapability($module, $request);
        if ($moduleCode === null) {
            return $forbidden ? $this->forbidden() : $this->notFound();
        }
        if (!$this->actions->has($moduleCode, 'save')) {
            return $this->notFound();
        }

        try {
            $editor = $this->modalLoader->load($moduleCode, $id);
        } catch (EditorModalLockedException $exception) {
            return $this->responses->json($this->authorizationResponses->recordLockedPayload($exception->lockedBy()), HttpStatusCode::CONFLICT->value);
        } catch (EditorEntityNotFoundException) {
            return $this->notFound();
        } catch (EditorCapabilityException $exception) {
            return $this->capabilityResponse($exception);
        }

        return $this->modalResponse($this->pages->modalEditor($moduleCode)->modalEdit($editor, $locale));
    }

    /**
     * Overuje module route, lifecycle, pristup a modalni presentation capability
     *
     * @return array{0:string|null,1:string,2:bool}
     */
    private function modalCapability(string $module, ServerRequestInterface $request): array
    {
        try {
            $definition = $this->routes->resolve($module);
        } catch (\RuntimeException) {
            return [null, '', false];
        }

        $moduleCode = $definition->code();
        if (!$this->modules->enabled($moduleCode) || !$this->pages->hasModalEditor($moduleCode)) {
            return [null, '', false];
        }
        if (!$this->moduleAccess->canAccess($definition)) {
            return [null, '', true];
        }

        return [$moduleCode, $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', '')), false];
    }

    /**
     * Vraci modalni HTML ve structured JSON odpovedi
     */
    private function modalResponse(ModulePage $page): ResponseInterface
    {
        return $this->responses->json([
            'success' => true,
            'modalHtml' => $this->views->content($page->view(), $page->data()),
        ]);
    }

    /**
     * Prevadi editorovou capability chybu na canonical JSON odpoved
     */
    private function capabilityResponse(EditorCapabilityException $exception): ResponseInterface
    {
        return $exception->status() === HttpStatusCode::FORBIDDEN->value
            ? $this->responses->json($this->authorizationResponses->forbiddenPayload(), HttpStatusCode::FORBIDDEN->value)
            : $this->notFound();
    }

    /**
     * Vraci canonical JSON odpoved pro nedostupny modalni cil
     */
    private function notFound(): ResponseInterface
    {
        return $this->responses->json($this->authorizationResponses->recordNotFoundPayload(), HttpStatusCode::NOT_FOUND->value);
    }

    /**
     * Vraci canonical JSON odpoved pro zakazany pristup k modulu
     */
    private function forbidden(): ResponseInterface
    {
        return $this->responses->json($this->authorizationResponses->forbiddenPayload(), HttpStatusCode::FORBIDDEN->value);
    }
}
