<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionAccessPolicyInterface;
use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Contract\ModuleActionLockBypassPolicyInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorRecordConflictException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Editor\Lock\EditorLockException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\System\Users\Policies\UsersEditorAccessPolicy;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Uklada zmeny uzivatele se self-service pristupem a omezenym bypass editor locku
 */
final class UsersEditorSaveAction implements ModuleActionHandlerInterface, ModuleActionAccessPolicyInterface, ModuleActionLockBypassPolicyInterface
{
    /**
     * Nastavuje dispatcher editoru a policy uzke self-service vyjimky
     */
    public function __construct(
        private readonly EditorDispatcher $editors,
        private readonly UsersEditorAccessPolicy $access,
    ) {}

    /**
     * Povoli ulozeni vlastniho profilu mimo bezne opravneni editoru
     */
    public function accessDecision(?int $id): ?bool
    {
        return $this->access->selfServiceDecision($id);
    }

    /**
     * Overuje, zda self-service payload muze obejit cizi editor lock
     *
     * @param array<string, mixed> $payload
     */
    public function canBypassForeignLock(?int $id, array $payload): bool
    {
        return $id !== null && $this->access->canSaveWithoutLock($id, $payload);
    }

    /**
     * Uklada editor a prevadi authorization, lock a validation chyby na action response
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        if ($id === null) {
            throw new RuntimeException('An entity ID is required.');
        }
        try {
            $outcome = $this->editors->update('system.users', $id, $payload);
        } catch (EditorCapabilityException $exception) {
            throw new ModuleActionException(
                $exception->statusCode(),
                $exception->error(),
                $exception->getMessage(),
            );
        } catch (EditorEntityNotFoundException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                $exception->getMessage(),
            );
        } catch (EditorLockException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::CONFLICT,
                AdminErrorCode::LOCK_CONFLICT,
                $exception->getMessage(),
            );
        } catch (EditorRecordConflictException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::CONFLICT,
                AdminErrorCode::LOCK_CONFLICT,
                $exception->getMessage(),
                recordConflict: true,
            );
        } catch (EditorValidationException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->messageKey()]);
        }
        if (!$outcome->isSaved()) {
            return ModuleActionResult::invalid($outcome->validation()->errors(), $outcome->input());
        }

        return ModuleActionResult::success($outcome->result()->messageKey(), $outcome->result()->data(), false);
    }
}
