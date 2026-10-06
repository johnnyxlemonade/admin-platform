<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use Lemonade\Admin\Event\DomainEvent;

/**
 * Definuje zapis auditnich udalosti
 */
interface AuditLogWriterInterface
{
    /**
     * Zapise auditni udalost
     */
    public function record(DomainEvent $event, AuditOperation $operation): void;
}
