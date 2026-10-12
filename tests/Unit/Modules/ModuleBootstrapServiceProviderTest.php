<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Modules;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\ModuleBootstrapServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\Exception\ContainerFrozenException;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
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
        ModuleLifecycleEvents::$events = [];
    }

    /**
     * Zachova discovery poradi pri registraci i bootu a odmita definice po freeze
     */
    public function testManifestProvidersRegisterBeforeFreezeAndBootInDiscoveryOrder(): void
    {
        $container = new Container();
        $provider = new ModuleBootstrapServiceProvider($this->catalog(
            new ModuleLifecycleManifest('first', FirstBootableManifestProvider::class),
            new ModuleLifecycleManifest('middle', NonBootableManifestProvider::class),
            new ModuleLifecycleManifest('last', LastBootableManifestProvider::class),
        ));

        $provider->register($container);

        self::assertSame(['register.first', 'register.middle', 'register.last'], ModuleLifecycleEvents::$events);

        $container->freeze();
        $provider->boot($container);

        self::assertSame([
            'register.first',
            'register.middle',
            'register.last',
            'boot.first.frozen',
            'boot.last.frozen',
            'boot.last.binding_rejected',
        ], ModuleLifecycleEvents::$events);
    }

    /**
     * Dokumentuje, ze requires manifestoveho provideru se mimo package boundary nevyhodnocuje
     */
    public function testManifestProviderDependenciesAreNotResolvedAcrossThePackageBoundary(): void
    {
        $container = new Container();
        $provider = new ModuleBootstrapServiceProvider($this->catalog(
            new ModuleLifecycleManifest('dependent', PackageBoundaryDependentManifestProvider::class),
        ));

        $provider->register($container);
        $container->freeze();
        $provider->boot($container);

        self::assertSame(['register.dependent', 'boot.dependent'], ModuleLifecycleEvents::$events);
    }

    /**
     * Vytvori katalog s presne zadanymi manifesty bez Composer discovery
     */
    private function catalog(ModuleManifestInterface ...$manifests): ModuleCatalog
    {
        $reflection = new ReflectionClass(ModuleCatalog::class);
        $catalog = $reflection->newInstanceWithoutConstructor();
        $manifestsProperty = new ReflectionProperty(ModuleCatalog::class, 'manifests');
        $manifestsProperty->setValue($catalog, $manifests);

        return $catalog;
    }
}

/**
 * Uchovava poradi lifecycle udalosti fixture manifestovych provideru
 */
final class ModuleLifecycleEvents
{
    /** @var list<string> */
    public static array $events = [];
}

/**
 * Deklaruje fixture manifest s urcenym runtime providerem
 */
final class ModuleLifecycleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje kod fixture modulu a jeho provider
     *
     * @param class-string<ServiceProviderInterface> $provider
     */
    public function __construct(string $code, string $provider)
    {
        parent::__construct($code, ModuleKind::System, $provider);
    }
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
        ModuleLifecycleEvents::$events[] = 'register.first';
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
            ModuleLifecycleEvents::$events[] = $event;
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
        ModuleLifecycleEvents::$events[] = 'register.middle';
    }

    /**
     * Zaznamena neplatny boot, pokud by jej bootstrap omylem volal
     */
    public function boot(ContainerInterface $container): void
    {
        unset($container);
        ModuleLifecycleEvents::$events[] = 'boot.middle';
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
        ModuleLifecycleEvents::$events[] = 'register.last';
    }

    /**
     * Zaznamena freeze a overi zamitnuti pozdni container definice
     */
    public function boot(ContainerInterface $container): void
    {
        if (!$container instanceof ContainerBuilderInterface || !$container->isFrozen()) {
            return;
        }

        ModuleLifecycleEvents::$events[] = 'boot.last.frozen';

        try {
            $container->singleton(ModuleLifecycleLateBinding::class, ModuleLifecycleLateBinding::class);
        } catch (ContainerFrozenException) {
            ModuleLifecycleEvents::$events[] = 'boot.last.binding_rejected';
        }
    }
}

/**
 * Predstavuje zakazanou pozdni definici fixture provideru
 */
final class ModuleLifecycleLateBinding {}

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
        ModuleLifecycleEvents::$events[] = 'register.dependent';
    }

    /**
     * Zaznamena boot provideru se zavislosti mimo package boundary
     */
    public function boot(ContainerInterface $container): void
    {
        unset($container);
        ModuleLifecycleEvents::$events[] = 'boot.dependent';
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
        ModuleLifecycleEvents::$events[] = 'register.dependency';
    }
}
