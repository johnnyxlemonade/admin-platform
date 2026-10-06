<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\System\Roles\Exceptions\RoleAuthorizationException;
use Lemonade\Admin\System\Roles\Exceptions\RoleNotFoundException;
use Lemonade\Admin\System\Roles\Services\RoleService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Prevadi soft delete role na shared action response
 */
final class RolesDeleteAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje sluzbu vlastnici deletion invarianty role
     */
    public function __construct(private readonly RoleService $roles) {}

    /**
     * Deleguje soft delete do sluzby a prelozi domenove odmitnuti pro Admin transport
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        if ($id === null) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Role not found.',
            );
        }
        try {
            $this->roles->delete($id);
        } catch (RoleNotFoundException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                $exception->getMessage(),
            );
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (RoleAuthorizationException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::PERMISSION_DENIED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }
        return ModuleActionResult::success('roles.actions.deleted');
    }
}
