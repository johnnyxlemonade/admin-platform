<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Audit;

use InvalidArgumentException;
use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

final class AuditEventPresentationRegistryTest extends TestCase
{
    public function testItResolvesRegisteredModuleOwnedPresentation(): void
    {
        $registry = new AuditEventPresentationRegistry();
        $presentation = new AuditEventPresentation('module.example.created', 'example.audit.events.created', AdminIcon::PlusLg);
        $registry->register($presentation);

        self::assertSame($presentation, $registry->presentation('module.example.created'));
        self::assertNull($registry->presentation('module.unknown'));
    }

    public function testItRejectsDuplicateEventCode(): void
    {
        $registry = new AuditEventPresentationRegistry();
        $registry->register(new AuditEventPresentation('module.example.created', 'example.audit.events.created'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('module.example.created');
        $registry->register(new AuditEventPresentation('module.example.created', 'other.audit.events.created'));
    }

    public function testItResolvesRegisteredSourceOwnedModulePresentation(): void
    {
        $registry = new AuditEventPresentationRegistry();
        $presentation = new AuditModulePresentation('module.example', 'example', 'example.module.name', AdminIcon::People);
        $registry->registerModule($presentation);

        self::assertSame($presentation, $registry->modulePresentation('module.example'));
        self::assertNull($registry->modulePresentation('module.unknown'));
    }

    public function testItRejectsDuplicateModuleCode(): void
    {
        $registry = new AuditEventPresentationRegistry();
        $registry->registerModule(new AuditModulePresentation('module.example', 'example', 'example.module.name'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('module.example');
        $registry->registerModule(new AuditModulePresentation('module.example', 'other', 'other.module.name'));
    }
}
