<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Authorization\EffectivePermissionGroupViewModelFactory;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\System\Users\Exceptions\UserNotFoundException;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Vytvari presentation nahled efektivnich opravneni pro zvolenou roli uzivatele
 */
final class UsersPermissionPreviewAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje sluzbu uzivatele a shared presenter permission skupin
     */
    public function __construct(
        private readonly UserService $users,
        private readonly EffectivePermissionGroupViewModelFactory $groups,
    ) {}

    /**
     * Vraci delegovatelny permission nahled nebo validation response bez mutace
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        if ($id === null || !isset($payload['role']) || !is_numeric($payload['role'])) {
            return ModuleActionResult::invalid(['role' => 'users.validation.role_invalid']);
        }
        try {
            return ModuleActionResult::success(null, $this->users->permissionPreview($id, (int) $payload['role'], $this->groups));
        } catch (UserNotFoundException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                $exception->getMessage(),
            );
        } catch (UserSafetyException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->translationKey()]);
        }
    }
}
