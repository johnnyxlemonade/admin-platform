<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Presentation\AdminFileUsageDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje explicitni image profil pro polymorfni generic file usage
 */
final class AdminFileUsageDefinitionTest extends TestCase
{
    /**
     * Overi ze generic usage muze deklarovat samostatny image profil
     */
    public function testGenericUsageExposesOptionalImageProfile(): void
    {
        $definition = new AdminFileUsageDefinition(
            moduleCode: 'cms.news',
            usage: 'attachment',
            kind: 'file',
            profile: 'admin-file',
            multiple: true,
            sortable: true,
            imageProfile: 'admin-image',
        );

        self::assertSame('admin-image', $definition->imageProfile());
    }

    /**
     * Overi ze cisty image usage neprijima polymorfni image profil
     */
    public function testImageUsageRejectsPolymorphicImageProfile(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AdminFileUsageDefinition(
            moduleCode: 'cms.news',
            usage: 'gallery',
            kind: 'image',
            profile: 'admin-image',
            multiple: true,
            sortable: true,
            imageProfile: 'admin-image',
        );
    }
}
