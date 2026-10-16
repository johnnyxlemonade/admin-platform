<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\ModuleBootstrapServiceProvider;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\System\Users\Dashboard\UsersDashboardWidgetProvider;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\Editor\UsersEditor;
use Lemonade\Admin\System\Users\Migrations\RegisterUsersModule;
use Lemonade\Admin\System\Users\ModuleManifest;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Admin\System\Users\UsersModuleProvider;
use Lemonade\Admin\System\Users\Validation\PermissionOverridesRule;
use Lemonade\Admin\System\Users\Validation\RoleAssignmentsRule;
use Lemonade\Admin\System\Users\Validation\UniqueUserEmailRule;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Overuje Users provider proti builder lifecycle a zachovani runtime contributions
 */
final class UsersModuleProviderTest extends TestCase
{
    public function testRegisterDefinesOnlyExplicitHttpAndActionComposition(): void
    {
        $builder = new ContainerBuilder();

        (new UsersModuleProvider())->register($builder);

        self::assertFalse($builder->isBound(UsersDashboardWidgetProvider::class));
        self::assertFalse($builder->isBound(UsersDataGrid::class));
        self::assertFalse($builder->isBound(UsersEditor::class));
        self::assertFalse($builder->isBound(UserService::class));
    }

    public function testStandardManifestBootstrapRegistersAllUsersContributionsAndAutowiresConcreteServices(): void
    {
        $container = new Container();
        $registries = $this->registerRuntimeRegistries($container);
        $container->instance(Environment::class, Environment::Testing);
        $container->instance(DebugMode::class, DebugMode::disabled());
        $container->instance(Path::class, new Path(dirname(__DIR__, 4)));
        $container->when(UserService::class)->needs(TotpCredentialService::class)->give(static fn() => null);
        $container->when(UserService::class)->needs(MfaAuthenticationService::class)->give(static fn() => null);
        $moduleBootstrap = new ModuleBootstrapServiceProvider($this->catalog(new ModuleManifest()));

        $moduleBootstrap->register($container);

        self::assertFalse($container->isBound(UserService::class));
        $firstUserService = $this->resolveConcrete($container, UserService::class);
        $secondUserService = $this->resolveConcrete($container, UserService::class);
        self::assertNotSame($firstUserService, $secondUserService);

        $container->freeze();

        $moduleBootstrap->boot($container);

        self::assertTrue($registries['translations']->resources() !== []);
        self::assertSame(
            realpath(__DIR__ . '/../../../../src/System/Users/Resources/views/editor.php'),
            $registries['views']->resolve('users', 'editor'),
        );
        self::assertTrue($registries['clientTranslations']->has('users'));
        self::assertTrue($registries['modules']->has('system.users'));
        self::assertTrue($registries['adminModules']->has('system.users'));
        self::assertTrue($registries['dataGrids']->has('system.users'));
        self::assertSame(UsersDataGrid::class, $registries['dataGrids']->providerServiceId('system.users'));
        self::assertTrue($registries['editors']->has('system.users'));
        self::assertSame(UsersEditor::class, $registries['editors']->providerServiceId('system.users'));
        self::assertTrue($registries['pages']->hasIndex('system.users'));
        self::assertTrue($registries['pages']->hasEditor('system.users'));

        foreach (['save', 'create', 'permissions-preview', 'activate', 'deactivate', 'delete', 'restore', 'mfa-reset'] as $action) {
            self::assertTrue($registries['actions']->has('system.users', $action));
        }

        self::assertNotNull($registries['audit']->modulePresentation('system.users'));
        self::assertNotNull($registries['audit']->presentation('system.users.created'));
        self::assertSame(UniqueUserEmailRule::class, $registries['rules']->get('system_users_unique_email'));
        self::assertSame(RoleAssignmentsRule::class, $registries['rules']->get('system_users_role'));
        self::assertSame(PermissionOverridesRule::class, $registries['rules']->get('system_users_permission_overrides'));
        self::assertInstanceOf(UniqueUserEmailRule::class, $this->resolveConcrete($container, UniqueUserEmailRule::class));
        self::assertInstanceOf(RoleAssignmentsRule::class, $this->resolveConcrete($container, RoleAssignmentsRule::class));
        self::assertInstanceOf(PermissionOverridesRule::class, $this->resolveConcrete($container, PermissionOverridesRule::class));
        self::assertNotNull($registries['permissions']->definition('system.users.mfa_reset'));
        self::assertSame('image', $registries['fileUsage']->require('system.users', 'thumbnail')->kind());
        self::assertContains(RegisterUsersModule::identifier(), $registries['migrations']->identifiers());

        self::assertSame([UsersDashboardWidgetProvider::class], $registries['dashboard']->providerServiceIds());
    }

    /**
     * Vytvori katalog s Users manifestem bez zavislosti na Composer discovery
     */
    private function catalog(ModuleManifest ...$manifests): ModuleCatalog
    {
        $reflection = new ReflectionClass(ModuleCatalog::class);
        $catalog = $reflection->newInstanceWithoutConstructor();
        $manifestsProperty = new ReflectionProperty(ModuleCatalog::class, 'manifests');
        $manifestsProperty->setValue($catalog, $manifests);

        return $catalog;
    }

    /**
     * Pripravi skutecne registry, do kterych Users provider zapisuje pri bootu
     *
     * @return array{
     *     translations: TranslationResourceRegistry,
     *     views: ViewResourceRegistry,
     *     clientTranslations: ClientTranslationGroupRegistry,
     *     modules: ModuleRegistry,
     *     adminModules: AdminModuleRegistry,
     *     dataGrids: DataGridRegistry,
     *     editors: EditorRegistry,
     *     pages: ModulePageRegistry,
     *     actions: ModuleActionRegistry,
     *     dashboard: DashboardWidgetRegistry,
     *     audit: AuditEventPresentationRegistry,
     *     rules: RuleRegistry,
     *     permissions: PermissionCatalogRegistry,
     *     fileUsage: AdminFileUsageRegistry,
     *     migrations: MigrationRegistry
     * }
     */
    private function registerRuntimeRegistries(Container $container): array
    {
        $registries = [
            'translations' => new TranslationResourceRegistry(),
            'views' => new ViewResourceRegistry(),
            'clientTranslations' => new ClientTranslationGroupRegistry(),
            'modules' => new ModuleRegistry(),
            'adminModules' => new AdminModuleRegistry(new AdminNavigationGroupRegistry()),
            'dataGrids' => new DataGridRegistry(),
            'editors' => new EditorRegistry(),
            'pages' => new ModulePageRegistry(),
            'actions' => new ModuleActionRegistry(),
            'dashboard' => new DashboardWidgetRegistry(),
            'audit' => new AuditEventPresentationRegistry(),
            'rules' => new RuleRegistry(),
            'permissions' => new PermissionCatalogRegistry(),
            'fileUsage' => new AdminFileUsageRegistry(),
            'migrations' => new MigrationRegistry($container),
        ];

        $container->instance(TranslationResourceRegistry::class, $registries['translations']);
        $container->instance(ViewResourceRegistry::class, $registries['views']);
        $container->instance(ClientTranslationGroupRegistry::class, $registries['clientTranslations']);
        $container->instance(ModuleRegistry::class, $registries['modules']);
        $container->instance(AdminModuleRegistry::class, $registries['adminModules']);
        $container->instance(DataGridRegistry::class, $registries['dataGrids']);
        $container->instance(EditorRegistry::class, $registries['editors']);
        $container->instance(ModulePageRegistry::class, $registries['pages']);
        $container->instance(ModuleActionRegistry::class, $registries['actions']);
        $container->instance(DashboardWidgetRegistry::class, $registries['dashboard']);
        $container->instance(AuditEventPresentationRegistry::class, $registries['audit']);
        $container->instance(RuleRegistry::class, $registries['rules']);
        $container->instance(PermissionCatalogRegistry::class, $registries['permissions']);
        $container->instance(AdminFileUsageRegistry::class, $registries['fileUsage']);
        $container->instance(MigrationRegistry::class, $registries['migrations']);

        return $registries;
    }

    /**
     * Resolves an autowired concrete while mocking only integration-port interfaces absent from this focused contract
     *
     * @template T of object
     * @param class-string<T> $service
     * @return T
     */
    private function resolveConcrete(Container $container, string $service): object
    {
        while (true) {
            try {
                return $container->get($service);
            } catch (ServiceNotFoundException $exception) {
                preg_match('/Interface "([^"]+)" has no container binding\./', $exception->getMessage(), $matches);
                if (!isset($matches[1])) {
                    throw new \LogicException($exception->getMessage(), previous: $exception);
                }

                $interface = $matches[1];
                self::assertTrue(interface_exists($interface));
                $container->instance($interface, $this->createMock($interface));
            }
        }
    }
}
