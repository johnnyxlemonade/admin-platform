<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use InvalidArgumentException;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleContributionLoader;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
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

    public function testItResolvesAnExplicitAdminRouteSegment(): void
    {
        $definition = $this->definition('system.users', 'users');
        $admin = $this->adminRegistry();
        $admin->register($definition);

        self::assertSame($definition, (new AdminModuleRouteResolver($admin))->resolve('users'));
    }

    public function testItRejectsDuplicateRouteSegments(): void
    {
        $registry = $this->adminRegistry();
        $registry->register($this->definition('system.first', 'users'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->definition('system.second', 'users'));
    }

    public function testItLoadsDeferredModuleContributionsOnceOnFirstLookup(): void
    {
        $loads = 0;
        $contributions = new AdminModuleContributionLoader();
        $registry = new AdminModuleRegistry(new AdminNavigationGroupRegistry(), $contributions);
        $definition = $this->definition('cms.type_1', 'type-news', AdminNavigationGroup::Content);
        $contributions->defer(static function () use ($registry, $definition, &$loads): void {
            $loads++;
            $registry->register($definition);
        });

        self::assertSame(0, $loads);
        self::assertSame([], $registry->registeredWithoutLoading());
        self::assertSame($definition, (new AdminModuleRouteResolver($registry))->resolve('type-news'));
        self::assertSame(1, $loads);
        self::assertSame([$definition], $registry->all());
        self::assertSame(1, $loads);
    }

    /**
     * Management route family vychazi jen z enumove navigation group
     */
    public function testItResolvesManagementRouteNamesFromMetadataNavigationGroup(): void
    {
        $admin = $this->adminRegistry();
        foreach ([
            $this->definition('custom.users', 'users', AdminNavigationGroup::System),
            $this->definition('custom.news', 'news', AdminNavigationGroup::Cms),
            $this->definition('custom.settings', 'settings', AdminNavigationGroup::Settings),
        ] as $definition) {
            $admin->register($definition);
        }

        $routes = new AdminModuleRouteResolver($admin);

        self::assertSame('admin.system.module.index', $routes->managementRouteName('users', 'index'));
        self::assertSame('admin.cms.module.edit', $routes->managementRouteName('news', 'edit'));
        self::assertSame('admin.settings.module.ajax.entity', $routes->managementRouteName('settings', 'ajax.entity'));
    }

    /**
     * Unknown segments miss normally, while database failures retain their identity
     */
    public function testRoutingDoesNotHideContributionFailures(): void
    {
        $failure = new \Lemonade\Framework\Database\Exception\DatabaseException('Catalog unavailable');
        $loader = new AdminModuleContributionLoader();
        $loader->defer(static function () use ($failure): void {
            throw $failure;
        });
        $registry = new AdminModuleRegistry(new AdminNavigationGroupRegistry(), $loader);
        $router = new \Lemonade\Framework\Routing\Router();
        (new \Lemonade\Admin\Routing\AdminModuleRouteRegistrar($registry))->registerRoutes($router);
        try {
            $router->match(new \Nyholm\Psr7\ServerRequest('GET', '/content/type-news'));
            self::fail('Bootstrap failure was hidden');
        } catch (\Lemonade\Framework\Database\Exception\DatabaseException $actual) {
            self::assertSame($failure, $actual);
        }
        $emptyRouter = new \Lemonade\Framework\Routing\Router();
        (new \Lemonade\Admin\Routing\AdminModuleRouteRegistrar($this->adminRegistry()))->registerRoutes($emptyRouter);
        $this->expectException(\Lemonade\Framework\Routing\Exception\RouteNotFoundException::class);
        $emptyRouter->match(new \Nyholm\Psr7\ServerRequest('GET', '/content/unknown'));
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
