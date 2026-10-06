<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Notification\NotificationLifecycleService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Adaptuje jednotlive management action na canonical notification lifecycle
 */
abstract class NotificationLifecycleAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje canonical lifecycle service
     */
    public function __construct(private readonly NotificationLifecycleService $notifications) {}

    /**
     * Deleguje lifecycle mutaci a mapuje jeji odmitnuti na action vysledek
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        unset($payload);
        if ($id === null) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Notification not found.',
            );
        }
        try {
            $this->mutate($id);
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'notifications.validation.not_found') {
                throw new ModuleActionException(
                    HttpStatusCode::NOT_FOUND,
                    AdminErrorCode::NOT_FOUND,
                    $exception->getMessage(),
                );
            }

            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }

        return ModuleActionResult::success($this->messageKey());
    }

    /**
     * Provadi lifecycle operaci definovanou konkretnim potomkem
     */
    abstract protected function mutate(int $id): void;

    /**
     * Urci lokalizovany vysledek konkretni lifecycle action
     */
    abstract protected function messageKey(): string;

    /**
     * Zpristupnuje canonical lifecycle service potomkum
     */
    protected function lifecycle(): NotificationLifecycleService
    {
        return $this->notifications;
    }
}
