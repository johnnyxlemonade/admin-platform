<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use InvalidArgumentException;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use PHPUnit\Framework\TestCase;

final class AdminModuleRegistryTest extends TestCase
{
    public function testAdminModuleMetadataRequiresACanonicalNavigationGroup(): void
    {
        $parameter = new \ReflectionParameter([AdminModuleMetadata::class, '__construct'], 'navigationGroup');
        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(AdminNavigationGroup::class, $type->getName());
        $methods = array_map(
            static fn(\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(AdminModuleMetadata::class))->getMethods(),
        );

        self::assertNotContains('destinationRoute', $methods);
        self::assertNotContains('destinationParameters', $methods);
    }

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

    /**
     * Management route family vychazi jen z enumove navigation group
     */
    public function testItResolvesManagementRouteNamesFromMetadataNavigationGroup(): void
    {
        $core = new ModuleRegistry();
        $admin = $this->adminRegistry();
        foreach ([
            $this->definition('custom.users', 'users', AdminNavigationGroup::System),
            $this->definition('custom.news', 'news', AdminNavigationGroup::Content),
            $this->definition('custom.settings', 'settings', AdminNavigationGroup::Settings),
        ] as $definition) {
            $core->register($definition);
            $admin->register($definition);
        }

        $routes = new AdminModuleRouteResolver($admin, $core);

        self::assertSame('admin.system.module.index', $routes->managementRouteName('users', 'index'));
        self::assertSame('admin.content.module.edit', $routes->managementRouteName('news', 'edit'));
        self::assertSame('admin.settings.module.ajax.entity', $routes->managementRouteName('settings', 'ajax.entity'));
    }

    private function definition(string $code, string $segment, AdminNavigationGroup $group = AdminNavigationGroup::System): AdminModuleDefinitionInterface
    {
        return new class ($code, $segment, $group) implements AdminModuleDefinitionInterface {
            public function __construct(
                private readonly string $code,
                private readonly string $segment,
                private readonly AdminNavigationGroup $group,
            ) {}

            public function code(): string
            {
                return $this->code;
            }

            public function adminMetadata(): AdminModuleMetadata
            {
                return new AdminModuleMetadata('example.module.name', AdminIcon::People, $this->group, 10, $this->segment);
            }

            public function permissionDefinitions(): array
            {
                return [];
            }

            public function auditEventDefinitions(): array
            {
                return [];
            }
        };
    }

    private function adminRegistry(): AdminModuleRegistry
    {
        return new AdminModuleRegistry(new AdminNavigationGroupRegistry());
    }
}
