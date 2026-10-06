<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

/**
 * Definuje posluchac domenove udalosti
 */
interface DomainEventListenerInterface
{
    /**
     * Zpracuje domenovou udalost
     */
    public function handle(DomainEvent $event): void;
}
