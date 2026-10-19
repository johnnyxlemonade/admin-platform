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

    private bool $noOp = false;

    /**
     * Zaznamena udalost skutecne domenove mutace
     */
    public function record(DomainEvent $event): void
    {
        if ($this->noOp) {
            throw new \LogicException('A no-op transaction cannot record an audit event.');
        }

        $this->events[] = $event;
    }

    /**
     * Oznaci zamceny transakcni pokus jako skutecny no-op
     */
    public function noOp(): void
    {
        if ($this->events !== []) {
            throw new \LogicException('An audited mutation cannot be marked as a no-op.');
        }

        $this->noOp = true;
    }

    /**
     * Vrati zaznamenane domenove udalosti
     *
     * @return list<DomainEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Rozpozna explicitne potvrzeny stav bez mutace
     */
    public function isNoOp(): bool
    {
        return $this->noOp;
    }
}
