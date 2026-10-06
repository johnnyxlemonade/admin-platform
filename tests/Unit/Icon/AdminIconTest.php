<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Icon;

use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

/**
 * Overuje canonical Bootstrap mapovani ikon administrace
 */
final class AdminIconTest extends TestCase
{
    /**
     * Overi representative semantic ikony a jejich CSS tridy
     */
    public function testCanonicalIconsExposeBootstrapIdentifiersAndClasses(): void
    {
        self::assertSame('plus-lg', AdminIcon::PlusLg->value);
        self::assertSame('bi bi-plus-lg', AdminIcon::PlusLg->cssClass());
        self::assertSame('download', AdminIcon::Export->value);
        self::assertSame('bi bi-download', AdminIcon::Export->cssClass());
        self::assertSame('display', AdminIcon::Display->value);
        self::assertSame('star', AdminIcon::Star->value);
        self::assertNull(AdminIcon::tryFrom('people-gear'));
    }
}
