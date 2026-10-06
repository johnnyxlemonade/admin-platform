<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Prevadi zalozeni uzivatele z editoru na action response s navigaci do detailu
 */
final class UsersEditorCreateAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje shared editor dispatcher pro create workflow
     */
    public function __construct(private readonly EditorDispatcher $editors) {}

    /**
     * Vytvari uzivatele a zachovava action kontrakt navigace na novy editor
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        if ($id !== null) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::UNSUPPORTED_ACTION,
                'This action does not accept an entity ID.',
            );
        }

        try {
            $outcome = $this->editors->create('system.users', $payload);
        } catch (EditorCapabilityException $exception) {
            throw new ModuleActionException(
                $exception->statusCode(),
                $exception->error(),
                $exception->getMessage(),
            );
        } catch (EditorValidationException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->messageKey()]);
        }
        if (!$outcome->isSaved()) {
            return ModuleActionResult::invalid($outcome->validation()->errors(), $outcome->input());
        }

        return ModuleActionResult::success($outcome->result()->messageKey(), $outcome->result()->data(), true);
    }
}
