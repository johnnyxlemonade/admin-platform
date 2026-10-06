<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Authorization\Console\PermissionsSyncCommand;
use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sluzby pro autorizaci, katalog opravneni a jeho synchronizaci
 */
final class CoreAuthorizationServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje RBAC resolver, policy, permission catalog sluzby a synchronizacni command
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(AuthorizationService::class, AuthorizationService::class);
        $container->singleton(
            AuthorizationResolverInterface::class,
            static fn(ContainerInterface $container): AuthorizationService => $container->get(AuthorizationService::class),
        );
        $container->singleton(AuthorizationDelegationPolicy::class, AuthorizationDelegationPolicy::class);
        $container->singleton(AuthorizationTargetPolicy::class, AuthorizationTargetPolicy::class);
        $container->singleton(PermissionCatalogRegistry::class, PermissionCatalogRegistry::class);
        $container->singleton(PermissionDependencyResolver::class, PermissionDependencyResolver::class);
        $container->singleton(PermissionOverrideNormalizer::class, PermissionOverrideNormalizer::class);
        $container->singleton(PermissionSyncService::class, PermissionSyncService::class);
        $container->singleton(PermissionsSyncCommand::class, PermissionsSyncCommand::class);
        if ($container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->registerDefinition(new CommandDefinition(
                name: 'permissions:sync',
                commandClass: PermissionsSyncCommand::class,
                description: 'Synchronizes module permission catalogs.',
            ));
        }
    }
}
