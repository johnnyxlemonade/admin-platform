<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Cms\Content\CmsContentEvents;

/**
 * Predava CMS udalosti standardnimu Admin auditnimu collectorovi
 */
final readonly class CmsAdminEvents implements CmsContentEvents
{
    /**
     * Nastavuje collector aktualni auditovane transakce
     */
    public function __construct(private TransactionalEventCollector $events) {}

    /**
     * Zaznamena CMS udalost se zachovanim identity modulu a entity
     *
     * @param array<string,mixed> $payload
     */
    public function record(string $event, string $moduleCode, string $entityType, int $entityId, array $payload = []): void
    {
        $this->events->record(new DomainEvent($event, $moduleCode, $entityType, (string) $entityId, $payload));
    }

    /**
     * Potvrdi, ze transakce neprovedla zadnou mutaci
     */
    public function noOp(): void
    {
        $this->events->noOp();
    }
}
