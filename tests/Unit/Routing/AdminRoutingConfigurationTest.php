<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Routing;

use InvalidArgumentException;
use Lemonade\Admin\Auth\Routing\AdminAuthRouteRegistrar;
use Lemonade\Admin\Dashboard\Routing\DashboardRouteRegistrar;
use Lemonade\Admin\Editor\Lock\EditorLockRouteRegistrar;
use Lemonade\Admin\Editor\Routing\EditorModalRouteRegistrar;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Notification\Routing\NotificationRouteRegistrar;
use Lemonade\Admin\Routing\AdminModuleRouteRegistrar;
use Lemonade\Admin\Routing\AdminRoutePrefixRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminRoutingConfigurationTest extends TestCase
{
    #[DataProvider('basePaths')]
    public function testItPrefixesAdminRoutesWithoutChangingTheirLogicalNames(string $basePath): void
    {
        $router = new Router();
        $registry = new AdminRouteRegistrarRegistry();
        $registry->register(new AdminAuthRouteRegistrar());
        $registry->register(new AdminRouteRegistrar());
        $registry->register(new DashboardRouteRegistrar());
        $registry->register(new EditorLockRouteRegistrar());
        $registry->register(new EditorModalRouteRegistrar());
        $registry->register(new AdminModuleRouteRegistrar($this->modules()));
        $registry->register(new NotificationRouteRegistrar());

        (new AdminRoutePrefixRegistrar(new AdminRoutingConfiguration($basePath), $registry))->registerRoutes($router);

        self::assertSame($basePath, $router->url('admin.dashboard'));
        self::assertSame($basePath . '/system/users', $router->url('admin.system.module.index', ['module' => 'users']));
        self::assertSame($basePath . '/api/datagrid/users', $router->url('admin.api.datagrid.index', ['module' => 'users']));
        self::assertSame($basePath . '/api/modal/languages/create', $router->url('admin.api.modal.create', ['module' => 'languages']));
        self::assertSame($basePath . '/api/modal/languages/15/edit', $router->url('admin.api.modal.edit', ['module' => 'languages', 'id' => 15]));
        self::assertSame($basePath . '/api/editor/languages/15/release', $router->url('admin.api.editor.release', ['module' => 'languages', 'id' => 15]));
        self::assertSame($basePath . '/api/editor/cleanup', $router->url('admin.api.editor.cleanup'));
        self::assertSame($basePath . '/api/notifications/indicator', $router->url('admin.api.notifications.indicator'));
        self::assertSame($basePath . '/login', $router->url('admin.login'));
        self::assertSame($basePath . '/system/users/ajax/42', $router->url('admin.system.module.ajax.entity', ['module' => 'users', 'id' => 42]));
        self::assertSame($basePath . '/resources/i18n/admin', $router->url('admin.resources.i18n', ['group' => 'admin']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function basePaths(): iterable
    {
        yield 'default host' => ['/admin'];
        yield 'alternative host' => ['/backoffice'];
    }

    #[DataProvider('invalidBasePaths')]
    public function testItRejectsAmbiguousOrNonPathBasePaths(string $basePath): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminRoutingConfiguration($basePath);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBasePaths(): iterable
    {
        yield 'empty path' => [''];
        yield 'root path' => ['/'];
        yield 'relative path' => ['admin'];
        yield 'query string' => ['/admin?tab=users'];
        yield 'fragment' => ['/admin#users'];
    }

    /**
     * Vytvari registry s builtin System moduly pro overeni canonical namespace
     */
    private function modules(): AdminModuleRegistry
    {
        $registry = (new \ReflectionClass(AdminModuleRegistry::class))->newInstanceWithoutConstructor();
        $definitions = [
            new \Lemonade\Admin\System\Audit\AuditModuleDefinition(),
            new \Lemonade\Admin\System\Languages\LanguagesModuleDefinition(),
            new \Lemonade\Admin\System\Modules\ModulesModuleDefinition(),
            new \Lemonade\Admin\System\Notifications\NotificationsModuleDefinition(),
            new \Lemonade\Admin\System\Roles\RolesModuleDefinition(),
            new \Lemonade\Admin\System\Translations\TranslationsModuleDefinition(),
            new \Lemonade\Admin\System\Users\UsersModuleDefinition(),
        ];
        $property = new \ReflectionProperty(AdminModuleRegistry::class, 'definitions');
        $property->setValue($registry, array_combine(array_map(static fn($definition): string => $definition->code(), $definitions), $definitions));

        return $registry;
    }
}
