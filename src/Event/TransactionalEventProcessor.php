<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Framework\Database\Database;

/**
 * Zpracuje udalosti po uspesnem dokonceni transakce
 */
final readonly class TransactionalEventProcessor
{
    public function __construct(
        private Database $database,
        private AuditLogWriterInterface $audit,
        private DomainEventDispatcher $dispatcher,
    ) {}

    /**
     * Persists audit events in the mutation transaction, then dispatches their secondary
     * side effects only after the transaction has committed.
     *
     * @template T
     * @param callable(TransactionalEventCollector):T $mutation
     * @return T
     */
    public function execute(AuditActor $actor, callable $mutation): mixed
    {
        /** @var array{result:T,events:list<DomainEvent>} $outcome */
        $outcome = $this->database->transaction(function () use ($actor, $mutation): array {
            $events = new TransactionalEventCollector();
            $result = $mutation($events);

            if ($events->events() === [] && !$events->isNoOp()) {
                throw new MissingAuditEventException('An audited operation must record at least one event.');
            }
            foreach ($events->events() as $event) {
                $this->audit->record($event, $actor);
            }

            return ['result' => $result, 'events' => $events->events()];
        });

        foreach ($outcome['events'] as $event) {
            $this->dispatcher->dispatch($event->withAuditActor($actor));
        }

        return $outcome['result'];
    }
}
