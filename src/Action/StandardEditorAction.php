<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorSaveOutcome;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Editor\Lock\EditorLockException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Provadi vytvoreni a ulozeni zaznamu pres editor
 */
final class StandardEditorAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavi editor a rezim provedeni akce
     */
    private function __construct(
        private readonly EditorDispatcher $editors,
        private readonly string $module,
        private readonly bool $creating,
    ) {}

    /**
     * Vytvori akci pro zalozeni noveho zaznamu
     */
    public static function create(EditorDispatcher $editors, string $module): self
    {
        return new self($editors, $module, true);
    }

    /**
     * Vytvori akci pro ulozeni existujiciho zaznamu
     */
    public static function save(EditorDispatcher $editors, string $module): self
    {
        return new self($editors, $module, false);
    }

    /**
     * Provede vytvoreni nebo ulozeni podle nastaveneho rezimu
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        if ($this->creating) {
            return $this->executeCreate($id, $payload);
        }

        return $this->executeSave($id, $payload);
    }

    /**
     * Vytvori zaznam a prevede vysledek editoru na vysledek akce
     *
     * @param array<string, mixed> $payload
     */
    private function executeCreate(?int $id, array $payload): ModuleActionResult
    {
        if ($id !== null) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::UNSUPPORTED_ACTION,
                'This action does not accept an entity ID.',
            );
        }

        try {
            $outcome = $this->editors->create($this->module, $payload);
        } catch (EditorCapabilityException $exception) {
            throw new ModuleActionException(
                $exception->statusCode(),
                $exception->error(),
                $exception->getMessage(),
            );
        } catch (EditorValidationException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->messageKey()]);
        }

        return $this->result($outcome);
    }

    /**
     * Ulozi zaznam a prevede vysledek editoru na vysledek akce
     *
     * @param array<string, mixed> $payload
     */
    private function executeSave(?int $id, array $payload): ModuleActionResult
    {
        if ($id === null) {
            throw new RuntimeException('An entity ID is required.');
        }

        try {
            $outcome = $this->editors->update($this->module, $id, $payload);
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
        } catch (EditorValidationException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->messageKey()]);
        }

        return $this->result($outcome);
    }

    /**
     * Prevede vysledek ulozeni editoru na vysledek akce
     */
    private function result(EditorSaveOutcome $outcome): ModuleActionResult
    {
        if (!$outcome->isSaved()) {
            return ModuleActionResult::invalid($outcome->validation()->errors(), $outcome->input());
        }

        return ModuleActionResult::success($outcome->result()->messageKey(), $outcome->result()->data());
    }
}
