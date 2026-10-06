<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action;

use Lemonade\Admin\Action\Contract\ModuleActionAccessPolicyInterface;
use Lemonade\Admin\Action\Contract\ModuleActionLockBypassPolicyInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Overuje a spousti registrovane admin akce modulu
 */
final class ModuleActionDispatcher
{
    /**
     * Nastavi sluzby potrebne pro overeni a spusteni akce
     */
    public function __construct(
        private readonly AdminModuleRouteResolver $routes,
        private readonly AdminModuleAccessPolicy $moduleAccess,
        private readonly ModuleManager $modules,
        private readonly ModuleActionRegistry $actions,
        private readonly AuthorizationService $authorization,
        private readonly EditorLockManager $locks,
        private readonly ModulePageRegistry $pages,
    ) {}

    /**
     * Overi modul, opravneni a zamek, pote spusti pozadovanou akci
     *
     * @param array<string, mixed> $request
     */
    public function dispatch(string $segment, ?int $id, array $request): ModuleActionResult
    {
        try {
            $module = $this->routes->resolve($segment);
        } catch (RuntimeException) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Module not found.',
            );
        }
        if (!$this->modules->enabled($module->code())) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Module is not available.',
            );
        }
        if (!$this->moduleAccess->canAccess($module)) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::PERMISSION_DENIED,
                'Permission denied.',
            );
        }
        $action = $request['action'] ?? null;
        if (!is_string($action) || !$this->actions->has($module->code(), $action)) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::UNSUPPORTED_ACTION,
                'Module action not found.',
            );
        }
        $registered = $this->actions->action($module->code(), $action);
        $decision = $registered['handler'] instanceof ModuleActionAccessPolicyInterface
            ? $registered['handler']->accessDecision($id)
            : null;
        if ($decision !== true && ($decision === false || !$this->authorization->hasPermission($registered['definition']->permission()))) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::PERMISSION_DENIED,
                'Permission denied.',
            );
        }
        $payload = $request['payload'] ?? [];
        if (!is_array($payload)) {
            throw new ModuleActionException(
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                AdminErrorCode::VALIDATION_FAILED,
                'Action payload must be an object.',
            );
        }

        $bypass = $registered['handler'] instanceof ModuleActionLockBypassPolicyInterface
            && $registered['handler']->canBypassForeignLock($id, $payload);
        if ($id !== null && !$bypass && !$this->locks->allowsAction($module->code(), (string) $id)) {
            throw new ModuleActionException(
                HttpStatusCode::CONFLICT,
                AdminErrorCode::LOCK_CONFLICT,
                'Record is locked by another user.',
                $this->locks->conflict($module->code(), (string) $id),
            );
        }

        $result = $registered['handler']->execute($id, $payload);
        if ($id === null && $action === 'create' && $result->successful() && $this->pages->hasEditor($module->code())) {
            $entityId = $result->data()['id'] ?? null;
            if (!is_int($entityId) && !is_numeric($entityId)) {
                throw new ModuleActionException(
                    HttpStatusCode::CONFLICT,
                    AdminErrorCode::LOCK_CONFLICT,
                    'Created record has no lockable identifier.',
                );
            }
            if (!$this->locks->acquire($module->code(), (string) $entityId)->acquired) {
                throw new ModuleActionException(
                    HttpStatusCode::CONFLICT,
                    AdminErrorCode::LOCK_CONFLICT,
                    'Created record could not be locked.',
                );
            }
        }

        return $result->withDefinitionDefaults($registered['definition']);
    }
}
