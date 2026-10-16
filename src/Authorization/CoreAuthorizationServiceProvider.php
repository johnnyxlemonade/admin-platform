<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Authorization\Console\PermissionsSyncCommand;
use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sluzby pro autorizaci, katalog opravneni a jeho synchronizaci
 */
final readonly class CoreAuthorizationServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapisuje RBAC resolver, policy, permission catalog sluzby a synchronizacni command
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->scoped(AuthorizationService::class, AuthorizationService::class);
        $container->alias(AuthorizationResolverInterface::class, AuthorizationService::class);
        $container->singleton(PermissionCatalogRegistry::class, PermissionCatalogRegistry::class);
    }

    /**
     * Registruje permissions CLI command po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        if (method_exists($container, 'isBound') && $container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->registerDefinition(new CommandDefinition(
                name: 'permissions:sync',
                commandClass: PermissionsSyncCommand::class,
                description: 'Synchronizes module permission catalogs.',
            ));
        }
    }
}
