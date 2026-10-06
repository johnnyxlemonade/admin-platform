<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Framework\Database\Database;

/**
 * Zapisuje udalosti do auditniho logu
 */
final class AuditLogService implements AuditLogWriterInterface
{
    public function __construct(private readonly Database $database) {}

    public function record(DomainEvent $event, AuditOperation $operation): void
    {
        $payload = json_encode($event->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->database->statement(
            'INSERT INTO system_audit_log(event_code, module_code, entity_type, entity_key, actor_type, actor_key, actor_user_id, payload, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$event->code(), $event->moduleCode(), $event->entityType(), $event->entityKey(), $operation->actor()->type()->value, $operation->actor()->key(), $operation->actor()->userId(), $payload, date('Y-m-d H:i:s')],
        );
    }
}
