<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

/**
 * Obsluhuje HTTP pozadavky pro editorlock
 */
final class EditorLockController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminModuleRouteResolver $routes,
        private readonly AdminModuleAccessPolicy $moduleAccess,
        private readonly ModuleManager $modules,
        private readonly EditorLockManager $locks,
        private readonly AdminAuthorizationResponseHandler $responses,
        private readonly Responses $httpResponses,
    ) {}

    /**
     * Zpracovava krok release v HTTP toku administrace
     */
    public function release(string $module, string $id): ResponseInterface
    {
        return $this->respond($module, $id);
    }

    /**
     * Zpracovava krok cleanup v HTTP toku administrace
     */
    public function cleanup(): ResponseInterface
    {
        $this->locks->releaseAllForCurrentUser();

        return $this->httpResponses->json(['success' => true]);
    }

    /**
     * Zpracovava krok respond v HTTP toku administrace
     */
    private function respond(string $segment, string $id): ResponseInterface
    {
        try {
            $module = $this->routes->resolve($segment);
        } catch (\RuntimeException) {
            return $this->httpResponses->json([
                'success' => false,
                'error' => ['code' => AdminErrorCode::MODULE_NOT_FOUND->value],
            ], HttpStatusCode::NOT_FOUND->value);
        }
        if (!$this->modules->enabled($module->code())) {
            return $this->httpResponses->json([
                'success' => false,
                'error' => ['code' => AdminErrorCode::MODULE_NOT_AVAILABLE->value],
            ], HttpStatusCode::NOT_FOUND->value);
        }
        if (!$this->moduleAccess->canAccess($module)) {
            return $this->httpResponses->json($this->responses->forbiddenPayload(), HttpStatusCode::FORBIDDEN->value);
        }
        if ($this->locks->release($module->code(), $id)) {
            return $this->httpResponses->json(['success' => true]);
        }
        $owner = $this->locks->conflict($module->code(), $id);
        if ($owner !== null) {
            return $this->httpResponses->json(
                $this->responses->recordLockedPayload($owner),
                HttpStatusCode::CONFLICT->value,
            );
        }

        return $this->httpResponses->json(['success' => true]);
    }
}
