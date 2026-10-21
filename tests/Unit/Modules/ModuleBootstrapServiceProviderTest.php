<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Modules;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Module\Contract\AdminModuleFileCapabilitiesInterface;
use Lemonade\Admin\Modules\Catalog\AvailableModule;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\Migration\ModuleMigrationManifestInterface;
use Lemonade\Admin\Modules\ModuleBootstrapServiceProvider;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Presentation\AdminFileCapabilities;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ContainerFrozenException;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Database\Schema\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Overuje lifecycle provideru objevenych pres modulove manifesty
 */
final class ModuleBootstrapServiceProviderTest extends TestCase
{
    /**
     * Vymaze udalosti fixture provideru pred kazdym overenim
     */
    protected function setUp(): void
    {
        ModuleBootstrapEvents::$events = [];
    }

    /**
     * Zachova discovery poradi pri registraci i bootu a odmita definice po freeze
     */
    public function testManifestProvidersRegisterBeforeFreezeAndBootInDiscoveryOrder(): void
    {
        $container = $this->container();
        $provider = new ModuleBootstrapServiceProvider($this->catalog(
            new ModuleBootstrapManifest('first', FirstBootableManifestProvider::class),
            new ModuleBootstrapManifest('middle', NonBootableManifestProvider::class),
            new ModuleBootstrapManifest('last', LastBootableManifestProvider::class),
        ));

        $provider->register($container);

        self::assertSame(['register.first', 'register.middle', 'register.last'], ModuleBootstrapEvents::$events);

        $container->freeze();
        $provider->boot($container);

        self::assertSame([
            'register.first',
            'register.middle',
            'register.last',
            'boot.first.frozen',
            'boot.last.frozen',
            'boot.last.binding_rejected',
        ], ModuleBootstrapEvents::$events);
    }

    /**
     * Dokumentuje, ze requires manifestoveho provideru se mimo package boundary nevyhodnocuje
     */
    public function testManifestProviderDependenciesAreNotResolvedAcrossThePackageBoundary(): void
    {
        $container = $this->container();
        $provider = new ModuleBootstrapServiceProvider($this->catalog(
            new ModuleBootstrapManifest('dependent', PackageBoundaryDependentManifestProvider::class),
        ));

        $provider->register($container);
        $container->freeze();
        $provider->boot($container);

        self::assertSame(['register.dependent', 'boot.dependent'], ModuleBootstrapEvents::$events);
    }

    /**
     * Registruje zakladni Admin contributions primo z canonical manifestu
     */
    public function testAdminManifestContributesMetadataPermissionsAuditAndFiles(): void
    {
        $container = $this->container();
        $adminModules = new AdminModuleRegistry(new AdminNavigationGroupRegistry());
        $permissions = new PermissionCatalogRegistry();
        $audit = new AuditEventPresentationRegistry();
        $fileUsages = new AdminFileUsageRegistry();
        $container->instance(AdminModuleRegistry::class, $adminModules);
        $container->instance(PermissionCatalogRegistry::class, $permissions);
        $container->instance(AuditEventPresentationRegistry::class, $audit);
        $container->instance(AdminFileUsageRegistry::class, $fileUsages);
        $provider = new ModuleBootstrapServiceProvider($this->catalog(new AdminManifestFixture()));

        $provider->register($container);
        $container->freeze();
        $provider->boot($container);

        self::assertSame('test.admin', $adminModules->definition('test.admin')->code());
        self::assertNotNull($permissions->definition('test.admin.view'));
        self::assertNotNull($audit->presentation('test.admin.updated'));
        self::assertTrue($fileUsages->require('test.admin', 'gallery')->sortable());
    }

    /**
     * Registruje migrace Composer modulu do standardniho globalniho registry
     */
    public function testAvailableModuleMigrationsJoinTheGlobalRegistry(): void
    {
        $container = $this->container();
        $provider = new ModuleBootstrapServiceProvider($this->catalog(new MigratingManifestFixture()));

        $provider->register($container);
        $container->freeze();
        $provider->boot($container);

        self::assertSame([FixtureModuleMigration::identifier()], $container->get(MigrationRegistry::class)->identifiers());
    }

    /**
     * Pripravi container se standardnim migration registry
     */
    private function container(): Container
    {
        $container = new Container();
        $container->instance(MigrationRegistry::class, new MigrationRegistry($container));

        return $container;
    }

    /**
     * Vytvori katalog s presne zadanymi manifesty bez Composer discovery
     */
    private function catalog(ModuleManifestInterface ...$manifests): ModuleCatalog
    {
        $reflection = new ReflectionClass(ModuleCatalog::class);
        $catalog = $reflection->newInstanceWithoutConstructor();
        $modulesProperty = new ReflectionProperty(ModuleCatalog::class, 'modules');
        $modulesProperty->setValue($catalog, array_map(
            static fn(ModuleManifestInterface $manifest): AvailableModule => new AvailableModule(
                manifest: $manifest,
                packageName: 'test/package',
                version: '1.0.0',
                description: 'Test module',
            ),
            $manifests,
        ));

        return $catalog;
    }
}

/**
 * Uchovava poradi lifecycle udalosti fixture manifestovych provideru
 */
final class ModuleBootstrapEvents
{
    /** @var list<string> */
    public static array $events = [];
}

/**
 * Deklaruje fixture manifest s urcenym runtime providerem
 */
final class ModuleBootstrapManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje kod fixture modulu a jeho provider
     *
     * @param class-string<ServiceProviderInterface> $provider
     */
    public function __construct(string $code, string $provider)
    {
        parent::__construct($code, $provider);
    }
}

/**
 * Deklaruje vsechny automaticke Admin contributions bootstrap testu
 */
final class AdminManifestFixture extends ModuleManifestDefinition implements AdminModuleDefinitionInterface, AdminModuleFileCapabilitiesInterface
{
    /**
     * Nastavuje canonical identitu a prazdny runtime provider
     */
    public function __construct()
    {
        parent::__construct('test.admin', NonBootableManifestProvider::class);
    }

    /**
     * Vraci Admin metadata fixture modulu
     */
    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            'test.admin.name',
            AdminIcon::JournalText,
            AdminNavigationGroup::Content,
            1,
            'test-admin',
        );
    }

    /**
     * Vraci jedine view opravneni fixture modulu
     */
    public function permissionDefinitions(): array
    {
        return [new PermissionDefinition('test.admin.view', 'test.admin', 'test.admin.permissions.view')];
    }

    /**
     * Vraci jednu auditni prezentaci fixture modulu
     */
    public function auditEventDefinitions(): array
    {
        return [new AuditEventDefinition('updated', 'test.admin.audit.updated')];
    }

    /**
     * Zapina pouze realne testovanou gallery capability
     */
    public function fileCapabilities(): AdminFileCapabilities
    {
        return new AdminFileCapabilities(gallery: true);
    }
}

/**
 * Deklaruje modul s migraci registrovanou standardnim runnerem
 */
final class MigratingManifestFixture extends ModuleManifestDefinition implements ModuleMigrationManifestInterface
{
    /**
     * Nastavuje identitu migration fixture modulu
     */
    public function __construct()
    {
        parent::__construct('test.migrating', NonBootableManifestProvider::class);
    }

    /**
     * Vraci jedinou fixture migraci
     */
    public function migrations(): array
    {
        return [FixtureModuleMigration::class];
    }
}

/**
 * Predstavuje modulovou migraci ve standardnim globalnim registry
 */
final readonly class FixtureModuleMigration implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator fixture migrace
     */
    public static function identifier(): string
    {
        return '20261021000000_fixture_module_migration';
    }

    /**
     * Neprovadi schema zmenu mimo overeni registrace
     */
    public function up(Schema $schema): void {}
}

/**
 * Registruje a bootuje prvni fixture manifestovy provider
 */
final class FirstBootableManifestProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zaznamena registraci prvniho fixture provideru
     */
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'register.first';
    }

    /**
     * Overi freeze pred bootem prvniho fixture provideru
     */
    public function boot(ContainerInterface $container): void
    {
        self::assertFrozen($container, 'boot.first.frozen');
    }

    /**
     * Zaznamena uspesne dosazeni framework freeze hranice
     */
    private static function assertFrozen(ContainerInterface $container, string $event): void
    {
        if ($container instanceof ContainerBuilderInterface && $container->isFrozen()) {
            ModuleBootstrapEvents::$events[] = $event;
        }
    }
}

/**
 * Poskytuje metodu boot bez lifecycle contractu
 */
final class NonBootableManifestProvider implements ServiceProviderInterface
{
    /**
     * Zaznamena registraci non-bootable fixture provideru
     */
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'register.middle';
    }

    /**
     * Zaznamena neplatny boot, pokud by jej bootstrap omylem volal
     */
    public function boot(ContainerInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'boot.middle';
    }
}

/**
 * Overuje, ze bootable provider nemuze po freeze pridat definici
 */
final class LastBootableManifestProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zaznamena registraci posledniho fixture provideru
     */
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'register.last';
    }

    /**
     * Zaznamena freeze a overi zamitnuti pozdni container definice
     */
    public function boot(ContainerInterface $container): void
    {
        if (!$container instanceof ContainerBuilderInterface || !$container->isFrozen()) {
            return;
        }

        ModuleBootstrapEvents::$events[] = 'boot.last.frozen';

        try {
            $container->singleton(ModuleBootstrapLateBinding::class, ModuleBootstrapLateBinding::class);
        } catch (ContainerFrozenException) {
            ModuleBootstrapEvents::$events[] = 'boot.last.binding_rejected';
        }
    }
}

/**
 * Predstavuje zakazanou pozdni definici fixture provideru
 */
final class ModuleBootstrapLateBinding {}

/**
 * Deklaruje nepodporovanou zavislost mimo seznam manifestovych provideru
 */
final class PackageBoundaryDependentManifestProvider implements ServiceProviderInterface, BootableServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Deklaruje dependency, kterou module bootstrap zatim nepredava do globalniho planneru
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [PackageBoundaryDependencyProvider::class];
    }

    /**
     * Zaznamena registraci provideru se zavislosti mimo package boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'register.dependent';
    }

    /**
     * Zaznamena boot provideru se zavislosti mimo package boundary
     */
    public function boot(ContainerInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'boot.dependent';
    }
}

/**
 * Predstavuje provider vyzadovany pres nepodporovanou package boundary zavislost
 */
final class PackageBoundaryDependencyProvider implements ServiceProviderInterface
{
    /**
     * Zaznamena registraci dependency provideru, pokud by ji planner zaradil
     */
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
        ModuleBootstrapEvents::$events[] = 'register.dependency';
    }
}
