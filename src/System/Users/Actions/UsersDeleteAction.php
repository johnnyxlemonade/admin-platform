<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\System\Users\Exceptions\UserNotFoundException;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Prevadi pozadavek na soft delete uzivatele do action transportu
 */
final class UsersDeleteAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje sluzby pro lokalni actor guard a smazani uzivatele
     */
    public function __construct(
        private readonly UserService $users,
        private readonly LocalActorGuard $actors,
    ) {}

    /**
     * Deleguje soft delete do service a prevadi domenove chyby na action response
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        unset($payload);
        if ($id === null) {
            throw new RuntimeException('An entity ID is required.');
        }
        try {
            $actor = $this->actors->requireLocalUser();
            $this->users->deleteUser($id, $actor->id());
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (UserNotFoundException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                $exception->getMessage(),
            );
        } catch (UserSafetyException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->translationKey()]);
        }

        return ModuleActionResult::success('users.actions.deleted');
    }
}
