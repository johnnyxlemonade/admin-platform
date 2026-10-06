<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Rozesila udalosti po dokonceni transakce
 */
final class DomainEventDispatcher
{
    /** @var list<DomainEventListenerInterface> */
    private array $listeners = [];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function listen(DomainEventListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            try {
                $listener->handle($event);
            } catch (Throwable $exception) {
                $this->logger->error('Domain event listener failed after commit.', [
                    'event' => $event->code(),
                    'listener' => $listener::class,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
