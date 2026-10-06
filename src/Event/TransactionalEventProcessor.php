<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Framework\Database\Database;

/**
 * Zpracuje udalosti po uspesnem dokonceni transakce
 */
final class TransactionalEventProcessor
{
    public function __construct(
        private readonly Database $database,
        private readonly AuditLogWriterInterface $audit,
        private readonly DomainEventDispatcher $dispatcher,
    ) {}

    /**
     * Persists audit events in the mutation transaction, then dispatches their secondary
     * side effects only after the transaction has committed.
     *
     * @template T
     * @param callable(TransactionalEventCollector):T $mutation
     * @return T
     */
    public function execute(AuditOperation $operation, callable $mutation): mixed
    {
        /** @var array{result:T,events:non-empty-list<DomainEvent>} $outcome */
        $outcome = $this->database->transaction(function () use ($operation, $mutation): array {
            $events = new TransactionalEventCollector();
            $result = $mutation($events);

            if ($events->events() === []) {
                throw new MissingAuditEventException('An audited operation must record at least one event.');
            }
            foreach ($events->events() as $event) {
                if ($event->moduleCode() !== $operation->moduleCode()) {
                    throw new \LogicException('An audit event module must match its operation.');
                }
                $this->audit->record($event, $operation);
            }

            return ['result' => $result, 'events' => $events->events()];
        });

        foreach ($outcome['events'] as $event) {
            $this->dispatcher->dispatch($event->withAuditActor($operation->actor()));
        }

        return $outcome['result'];
    }
}
