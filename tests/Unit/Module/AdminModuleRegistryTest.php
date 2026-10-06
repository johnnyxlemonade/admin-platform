<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use InvalidArgumentException;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigationGroupDefinition;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use PHPUnit\Framework\TestCase;

final class AdminModuleRegistryTest extends TestCase
{
    public function testItResolvesAnExplicitAdminRouteSegmentToTheCoreModule(): void
    {
        $definition = $this->definition('system.users', 'users');
        $core = new ModuleRegistry();
        $admin = $this->adminRegistry();
        $core->register($definition);
        $admin->register($definition);

        self::assertSame($definition, (new AdminModuleRouteResolver($admin, $core))->resolve('users'));
    }

    public function testItRejectsDuplicateRouteSegments(): void
    {
        $registry = $this->adminRegistry();
        $registry->register($this->definition('system.first', 'users'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->definition('system.second', 'users'));
    }

    private function definition(string $code, string $segment): AdminModuleDefinitionInterface
    {
        return new class ($code, $segment) implements AdminModuleDefinitionInterface {
            public function __construct(
                private readonly string $code,
                private readonly string $segment,
            ) {}

            public function code(): string
            {
                return $this->code;
            }

            public function adminMetadata(): AdminModuleMetadata
            {
                return new AdminModuleMetadata('example.module.name', AdminIcon::People, 'system', 10, 'admin.module.index', $this->segment);
            }
        };
    }

    private function adminRegistry(): AdminModuleRegistry
    {
        $groups = new AdminNavigationGroupRegistry();
        $groups->register(new AdminNavigationGroupDefinition('system', 'admin.navigation.system', 50, AdminIcon::Gear));

        return new AdminModuleRegistry($groups);
    }
}
