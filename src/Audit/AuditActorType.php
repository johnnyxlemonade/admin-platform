<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

/**
 * Urcuje typ aktora auditni udalosti
 */
enum AuditActorType: string
{
    case User = 'user';
    case External = 'external';
    case System = 'system';
    case Cron = 'cron';
    case Migration = 'migration';
}
