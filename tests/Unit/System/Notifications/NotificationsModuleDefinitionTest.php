<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\System\Notifications\NotificationsModuleDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje deklaraci auditnich udalosti managementu oznameni
 */
final class NotificationsModuleDefinitionTest extends TestCase
{
    public function testItDeclaresPresentationsForAllEmittedNotificationEvents(): void
    {
        $definitions = (new NotificationsModuleDefinition())->auditEventDefinitions();

        self::assertSame([
            'published',
            'updated',
            'activated',
            'deactivated',
            'deleted',
            'restored',
            'display_reset',
        ], array_map(static fn(AuditEventDefinition $definition): string => $definition->suffix, $definitions));
    }
}
