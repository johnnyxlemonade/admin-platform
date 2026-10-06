<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Presentation\AdminFileAuditPresentationRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * Overuje lokalizovanou prezentaci auditnich udalosti shared souboru
 */
final class AdminFileAuditPresentationRegistrarTest extends TestCase
{
    /**
     * Registruje preklady a ikony pro vsechny shared file mutace
     */
    public function testItRegistersAllSharedFileAuditEvents(): void
    {
        $presentations = new AuditEventPresentationRegistry();
        (new AdminFileAuditPresentationRegistrar($presentations))->register();

        self::assertSame('admin.files.audit.events.uploaded', $presentations->presentation('system.files.uploaded')?->translationKey());
        self::assertSame(AdminIcon::PencilSquare, $presentations->presentation('system.files.replaced')?->icon());
        self::assertSame('admin.files.audit.events.removed', $presentations->presentation('system.files.removed')?->translationKey());
        self::assertSame('admin.files.audit.events.renamed', $presentations->presentation('system.files.renamed')?->translationKey());
        self::assertNull($presentations->presentation('system.files.restored'));
    }
}
