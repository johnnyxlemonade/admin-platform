<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Console\ModuleInstallCommand;
use Lemonade\Admin\Modules\Console\ModuleMigrateCommand;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje katalog, stav a lifecycle modulu aplikace
 */
final readonly class CoreModuleServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapisuje registry, persistence, lifecycle sluzby a CLI commandy modulu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ModuleRegistry::class, ModuleRegistry::class);
        $container->singleton(ModuleCatalog::class, ModuleCatalog::class);
        $container->singleton(ModuleStateResolver::class, ModuleStateResolver::class);
    }

    /**
     * Registruje module CLI commandy po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        if (method_exists($container, 'isBound') && $container->isBound(CommandRegistry::class)) {
            $commands = $container->get(CommandRegistry::class);
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
    }
}
