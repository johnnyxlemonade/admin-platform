<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Modules;

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Modules\Definition\ModuleDefinitionInterface;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleRegistryTest extends TestCase
{
    public function testItReportsRegisteredDefinitionsWithoutUsingRuntimeState(): void
    {
        $registry = new ModuleRegistry();
        $definition = new class implements ModuleDefinitionInterface {
            public function code(): string
            {
                return 'cms.example';
            }
        };

        $registry->register($definition);

        self::assertTrue($registry->has('cms.example'));
        self::assertFalse($registry->has('cms.missing'));
        self::assertSame($definition, $registry->definition('cms.example'));
        self::assertSame([$definition], $registry->all());
    }

    public function testDefinitionRejectsAnUnregisteredModule(): void
    {
        $registry = new ModuleRegistry();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cms.missing');

        $registry->definition('cms.missing');
    }

    public function testItAcceptsAModuleThatAlsoProvidesAdminMetadataWithoutKnowingThatMetadata(): void
    {
        $registry = new ModuleRegistry();
        $definition = new class implements \Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface {
            public function code(): string
            {
                return 'system.example';
            }

            public function adminMetadata(): \Lemonade\Admin\Module\AdminModuleMetadata
            {
                return new \Lemonade\Admin\Module\AdminModuleMetadata('example.module.name', AdminIcon::People, 'system', 10, 'admin.module.index', 'examples');
            }
        };
        $registry->register($definition);

        self::assertSame($definition, $registry->definition('system.example'));
    }
}
