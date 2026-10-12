<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Navigation;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Module\AdminNavigationGroup as AdminNavigationGroupCode;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Feature\FeatureProviderRegistry;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Navigation\AdminNavigationEntryInterface;
use Lemonade\Admin\Navigation\AdminNavigationGroup;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Navigation\AdminNavigationItem;
use Lemonade\Admin\System\Languages\LanguagesModuleDefinition;
use Lemonade\Admin\System\Roles\RolesModuleDefinition;
use Lemonade\Admin\System\SystemModuleServiceProvider;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class AdminNavigationTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryCatalogRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryCatalogRoots as $root) {
            @unlink($root . '/storage/cache/modules.php');
            @rmdir($root . '/storage/cache');
            @rmdir($root . '/storage');
            @rmdir($root);
        }
        $this->temporaryCatalogRoots = [];
    }

    public function testItOrdersCanonicalGroupsAfterStandaloneItems(): void
    {
        $navigation = $this->navigation([
            $this->definition('system.last', AdminNavigationGroupCode::System, 30),
            $this->definition('system.group', AdminNavigationGroupCode::System, 10),
            $this->definition('system.first', AdminNavigationGroupCode::System, 10),
        ]);

        self::assertSame(
            ['standalone:dashboard', 'standalone:notifications', 'group:system'],
            array_map(static fn(AdminNavigationEntryInterface $entry): string => $entry->key(), $navigation->items()),
        );
    }

    public function testItOrdersItemsInsideAGroupByOrderThenModuleCode(): void
    {
        $navigation = $this->navigation([
            $this->definition('system.beta', AdminNavigationGroupCode::System, 20),
            $this->definition('system.third', AdminNavigationGroupCode::System, 30),
            $this->definition('system.alpha', AdminNavigationGroupCode::System, 20),
            $this->definition('system.first', AdminNavigationGroupCode::System, 10),
        ]);
        $entries = $navigation->items();
        $group = $entries[2] ?? null;

        self::assertInstanceOf(AdminNavigationGroup::class, $group);
        self::assertSame(
            ['system.first', 'system.alpha', 'system.beta', 'system.third'],
            array_map(static fn(AdminNavigationItem $item): ?string => $item->moduleCode(), $group->items()),
        );
    }

    public function testItOmitsAGroupWhoseOnlyItemIsNotVisible(): void
    {
        $navigation = $this->navigation([$this->definition('system.private', AdminNavigationGroupCode::System, 10, true)], false);

        self::assertSame(
            ['standalone:dashboard', 'standalone:notifications'],
            array_map(static fn(AdminNavigationEntryInterface $entry): string => $entry->key(), $navigation->items()),
        );
    }

    public function testItExposesTheNavigationViewModelDataWithoutLegacyArrays(): void
    {
        $navigation = $this->navigation([
            $this->definition('system.grouped', AdminNavigationGroupCode::System, 10),
        ]);
        $entries = $navigation->items();

        self::assertContainsOnlyInstancesOf(AdminNavigationEntryInterface::class, $entries);
        self::assertInstanceOf(AdminNavigationItem::class, $entries[1]);
        self::assertSame('admin.notifications.title', $entries[1]->labelKey());
        self::assertSame('admin.notifications', $entries[1]->route());
        self::assertSame(AdminIcon::Bell, $entries[1]->icon());

        self::assertInstanceOf(AdminNavigationGroup::class, $entries[2]);
        self::assertSame('system', $entries[2]->code());
        self::assertSame('admin.navigation.system', $entries[2]->labelKey());
        self::assertSame(AdminIcon::Gear, $entries[2]->icon());
        self::assertSame(50, $entries[2]->order());
        self::assertCount(1, $entries[2]->items());
        self::assertSame('system.grouped', $entries[2]->items()[0]->moduleCode());

    }

    public function testItProvidesTheCanonicalSystemGroup(): void
    {
        $groups = new AdminNavigationGroupRegistry();
        $definition = $groups->definition('system');

        self::assertSame('admin.navigation.system', $definition->nameKey());
        self::assertSame(50, $definition->order());
        self::assertSame(AdminIcon::Gear, $definition->icon());
        self::assertTrue($groups->has(AdminNavigationGroupCode::Settings->value));
    }

    /**
     * Standardni lifecycle modulu zachovava aktivni polozku podle resolved metadata
     */
    public function testItMarksTheResolvedModuleInsteadOfMatchingUrlsByPrefix(): void
    {
        $metadata = new AdminModuleMetadata(
            nameKey: 'users.module.name',
            icon: AdminIcon::People,
            navigationGroup: AdminNavigationGroupCode::System,
            navigationOrder: 10,
            routeSegment: 'users',
        );
        $item = new AdminNavigationItem(
            key: 'module:system.users',
            order: 10,
            label: 'Users',
            labelKey: 'users.module.name',
            route: 'admin.system.module.index',
            routeParameters: ['module' => 'users'],
            routePrefix: false,
            icon: AdminIcon::People,
            moduleCode: 'system.users',
            moduleNavigationGroup: AdminNavigationGroupCode::System,
            moduleRouteSegment: 'users',
        );

        self::assertTrue($item->isActiveFor($metadata));
        self::assertFalse($item->isActiveFor(null));
    }

    /**
     * Index, create a edit standardnich modulu drzi stejnou polozku pres resolved metadata
     */
    public function testItKeepsTheManagementNavigationItemActiveAcrossTheStandardLifecycle(): void
    {
        foreach ([
            new UsersModuleDefinition(),
            new RolesModuleDefinition(),
            new LanguagesModuleDefinition(),
        ] as $definition) {
            $registry = new AdminModuleRegistry($this->navigationGroups());
            $registry->register($definition);
            $metadata = (new AdminModuleRouteResolver($registry, new ModuleRegistry()))
                ->metadata($definition->adminMetadata()->routeSegment());
            $item = new AdminNavigationItem(
                key: 'module:' . $definition->code(),
                order: $metadata->navigationOrder(),
                label: $metadata->nameKey(),
                labelKey: $metadata->nameKey(),
                route: 'admin.' . $metadata->navigationGroup->value . '.module.index',
                routeParameters: ['module' => $metadata->routeSegment()],
                routePrefix: false,
                icon: $metadata->icon(),
                moduleCode: $definition->code(),
                moduleNavigationGroup: $metadata->navigationGroup,
                moduleRouteSegment: $metadata->routeSegment(),
            );

            foreach (['index', 'create', 'edit'] as $lifecycleAction) {
                self::assertTrue(
                    $item->isActiveFor($metadata),
                    sprintf('%s must keep %s active.', $lifecycleAction, $definition->code()),
                );
            }
        }
    }

    /** @param list<AdminModuleDefinitionInterface> $definitions */
    private function navigation(array $definitions, bool $superAdmin = true): AdminNavigation
    {
        $groups = $this->navigationGroups();
        $adminModules = new AdminModuleRegistry($groups);
        foreach ($definitions as $definition) {
            $adminModules->register($definition);
        }
        $principal = new class implements CurrentPrincipalProviderInterface {
            public function currentPrincipal(): AdminPrincipalInterface
            {
                return new LocalAdminPrincipal($this->currentUser());
            }

            public function currentUser(): AuthenticatedUser
            {
                return new AuthenticatedUser(1, 'administrator@example.test');
            }
        };
        $database = new Database($this->connection($superAdmin), $this->createMock(DatabaseDriverInterface::class));
        $authorization = new AuthorizationService($principal, $database);

        return new AdminNavigation(
            new ModuleManager(new FeatureProviderRegistry(), $this->states($definitions, $database)),
            $authorization,
            $adminModules,
            new AdminModuleRouteResolver($adminModules, new ModuleRegistry()),
            new AdminModuleAccessPolicy($authorization, $principal),
            $groups,
            new class implements TranslatorInterface {
                public function setLocale(?string $locale): self
                {
                    return $this;
                }

                public function locale(): string
                {
                    return 'cs';
                }

                public function get(string $key, array $replacements = [], ?string $locale = null): string
                {
                    return $key;
                }

                public function group(string $group, ?string $locale = null): array
                {
                    return [];
                }

                public function all(?string $locale = null): array
                {
                    return [];
                }
            },
        );
    }

    private function navigationGroups(): AdminNavigationGroupRegistry
    {
        return new AdminNavigationGroupRegistry();
    }

    private function definition(string $code, AdminNavigationGroupCode $group, int $order, bool $superAdminOnly = false): AdminModuleDefinitionInterface
    {
        return new class ($code, $group, $order, $superAdminOnly) implements AdminModuleDefinitionInterface {
            public function __construct(
                private readonly string $code,
                private readonly AdminNavigationGroupCode $group,
                private readonly int $order,
                private readonly bool $superAdminOnly,
            ) {}

            public function code(): string
            {
                return $this->code;
            }

            public function adminMetadata(): AdminModuleMetadata
            {
                return new AdminModuleMetadata('example.module.name', AdminIcon::People, $this->group, $this->order, str_replace('.', '-', $this->code), null, $this->superAdminOnly);
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

    /** @param list<AdminModuleDefinitionInterface> $definitions */
    private function states(array $definitions, Database $database): ModuleStateResolver
    {
        $root = sys_get_temp_dir() . '/admin-navigation-modules-' . bin2hex(random_bytes(8));
        mkdir($root . '/storage/cache', 0775, true);
        $this->temporaryCatalogRoots[] = $root;
        NavigationManifestFixture::configure(array_map(static fn(AdminModuleDefinitionInterface $definition): string => $definition->code(), $definitions));
        $catalog = new ModuleCatalog((new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']));
        file_put_contents($catalog->cachePath(), '<?php return ' . var_export(array_fill(0, count($definitions), NavigationManifestFixture::class), true) . ';');

        return new ModuleStateResolver($catalog, new ModuleModel($database));
    }

    private function connection(bool $superAdmin): ConnectionInterface
    {
        return new class ($superAdmin) implements ConnectionInterface {
            public function __construct(private readonly bool $superAdmin) {}

            public function select(string $sql, array $bindings = []): array
            {
                return $this->superAdmin ? [['allowed' => 1]] : [];
            }

            public function cursor(string $sql, array $bindings = []): \Generator
            {
                yield from [];
            }

            public function statement(string $sql, array $bindings = []): int
            {
                return 0;
            }

            public function beginTransaction(): void {}

            public function commit(): void {}

            public function rollBack(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function transaction(callable $callback): mixed
            {
                return $callback($this);
            }

            public function lastInsertId(): int|string|null
            {
                return null;
            }

            public function affectedRows(): int
            {
                return 0;
            }

            public function reconnect(): void {}

            public function close(): void {}

            public function serverVersion(): string
            {
                return 'test';
            }

            public function escapeString(string $value): string
            {
                return $value;
            }
        };
    }
}

final class NavigationManifestFixture extends ModuleManifestDefinition
{
    /** @var list<string> */
    private static array $codes = [];

    /** @param list<string> $codes */
    public static function configure(array $codes): void
    {
        self::$codes = $codes;
    }

    public function __construct()
    {
        $code = array_shift(self::$codes);
        if (!is_string($code)) {
            throw new \LogicException('Navigation manifest fixture was instantiated too many times.');
        }

        parent::__construct($code, ModuleKind::System, SystemModuleServiceProvider::class);
    }
}
