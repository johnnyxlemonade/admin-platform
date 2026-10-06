<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Catalog\ModuleManifestDiscovery;
use Lemonade\Admin\Modules\Console\ModuleDiscoverCommand;
use Lemonade\Admin\Modules\Console\ModuleInstallCommand;
use Lemonade\Admin\Modules\Console\ModuleMigrateCommand;
use Lemonade\Admin\Modules\Feature\FeatureProviderRegistry;
use Lemonade\Admin\Modules\Feature\ModuleFeatureLifecycleService;
use Lemonade\Admin\Modules\Feature\ModuleFeatureStateResolver;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Admin\Modules\Migration\ModuleMigrationRunner;
use Lemonade\Admin\Modules\Persistence\ModuleFeatureModel;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Admin\Modules\Persistence\ModuleRoutePrefixModel;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Modules\Routing\ModuleRoutePrefixLifecycleService;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje katalog, stav a lifecycle modulu aplikace
 */
final class CoreModuleServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje registry, persistence, lifecycle sluzby a CLI commandy modulu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ModuleRegistry::class, ModuleRegistry::class);
        $container->singleton(ModuleCatalog::class, ModuleCatalog::class);
        $container->singleton(ModuleManifestDiscovery::class, ModuleManifestDiscovery::class);
        $container->singleton(ModuleModel::class, ModuleModel::class);
        $container->singleton(ModuleFeatureModel::class, ModuleFeatureModel::class);
        $container->singleton(ModuleRoutePrefixModel::class, ModuleRoutePrefixModel::class);
        $container->singleton(ModuleStateResolver::class, ModuleStateResolver::class);
        $container->singleton(ModuleFeatureStateResolver::class, ModuleFeatureStateResolver::class);
        $container->singleton(ModuleFeatureLifecycleService::class, ModuleFeatureLifecycleService::class);
        $container->singleton(ModuleRoutePrefixLifecycleService::class, ModuleRoutePrefixLifecycleService::class);
        $container->singleton(ModuleMigrationRunner::class, ModuleMigrationRunner::class);
        $container->singleton(ModuleLifecycleService::class, ModuleLifecycleService::class);
        $container->singleton(FeatureProviderRegistry::class, FeatureProviderRegistry::class);
        $container->singleton(ModuleDiscoverCommand::class, ModuleDiscoverCommand::class);
        $container->singleton(ModuleInstallCommand::class, ModuleInstallCommand::class);
        $container->singleton(ModuleMigrateCommand::class, ModuleMigrateCommand::class);
        if ($container->isBound(CommandRegistry::class)) {
            $commands = $container->get(CommandRegistry::class);
            $commands->registerDefinition(new CommandDefinition(
                name: 'modules:discover',
                commandClass: ModuleDiscoverCommand::class,
                description: 'Writes a diagnostic snapshot of Composer module manifests.',
            ));
            $commands->registerDefinition(new CommandDefinition(
                name: 'modules:install',
                commandClass: ModuleInstallCommand::class,
                description: 'Installs one discovered optional module.',
            ));
            $commands->registerDefinition(new CommandDefinition(
                name: 'modules:migrate',
                commandClass: ModuleMigrateCommand::class,
                description: 'Runs pending migrations for installed optional modules.',
            ));
        }
        $container->singleton(
            ModuleManager::class,
            static fn(ContainerInterface $container): ModuleManager => new ModuleManager(
                $container->get(FeatureProviderRegistry::class),
                $container->get(ModuleStateResolver::class),
                $container->get(ModuleFeatureModel::class),
                $container->get(ModuleFeatureStateResolver::class),
            ),
        );
    }
}
