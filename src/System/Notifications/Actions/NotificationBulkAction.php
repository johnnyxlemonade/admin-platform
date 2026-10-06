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
use RuntimeException;

/**
 * Adaptuje overeny vyber management zaznamu na hromadnou lifecycle operaci
 */
abstract class NotificationBulkAction implements ModuleActionHandlerInterface
{
    private const MAXIMUM_BATCH_SIZE = 100;

    /**
     * Nastavuje canonical lifecycle service pro hromadne mutace
     */
    public function __construct(private readonly NotificationLifecycleService $notifications) {}

    /**
     * Overuje vyber a deleguje hromadnou mutaci lifecycle service
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        unset($id);

        try {
            $ids = $this->ids($payload);
            $this->perform($ids);
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(HttpStatusCode::FORBIDDEN, AdminErrorCode::LOCAL_ACTOR_REQUIRED, $exception->getMessage());
        } catch (RuntimeException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }

        return $this->result();
    }

    /**
     * Provadi konkretni lifecycle operaci nad overenym vyberem
     *
     * @param list<int> $ids
     */
    abstract protected function perform(array $ids): void;

    /**
     * Sklada standardni uspechovou odpoved action transportu
     */
    protected function result(): ModuleActionResult
    {
        return ModuleActionResult::success($this->messageKey());
    }

    /**
     * Urci lokalizovany vysledek konkretni action
     */
    abstract protected function messageKey(): string;

    /**
     * Zpristupnuje canonical lifecycle service potomkum
     */
    protected function lifecycle(): NotificationLifecycleService
    {
        return $this->notifications;
    }

    /**
     * Normalizuje validni celoiselna ID vyberu z action payloadu
     *
     * @param array<string, mixed> $payload
     * @return list<int>
     */
    private function ids(array $payload): array
    {
        $ids = $payload['ids'] ?? null;
        if (!is_array($ids) || $ids === [] || count($ids) > self::MAXIMUM_BATCH_SIZE) {
            throw new RuntimeException('notifications.validation.bulk_ids_invalid');
        }

        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new RuntimeException('notifications.validation.bulk_ids_invalid');
            }
        }
        if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            throw new RuntimeException('notifications.validation.bulk_ids_invalid');
        }

        return array_values($ids);
    }
}
