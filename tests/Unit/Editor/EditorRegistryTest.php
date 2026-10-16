<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor;

use Lemonade\Admin\Editor\EditorRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Overuje root-safe katalog editor provideru
 */
final class EditorRegistryTest extends TestCase
{
    /**
     * Overuje registraci service ID bez runtime instance
     */
    public function testItRegistersProviderServiceIdByStableModuleCode(): void
    {
        $registry = new EditorRegistry();
        $registry->register('cms.example', 'cms.example.editor');

        self::assertTrue($registry->has('cms.example'));
        self::assertFalse($registry->has('cms.without-editor'));
        self::assertSame('cms.example.editor', $registry->providerServiceId('cms.example'));
    }
}
