<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

/**
 * Sbira udalosti behem transakce
 */
final class TransactionalEventCollector
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<DomainEvent> */
    public function events(): array
    {
        return $this->events;
    }
}
