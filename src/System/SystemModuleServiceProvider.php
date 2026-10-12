<?php

declare(strict_types=1);

namespace Lemonade\Admin\System;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Navigation\AdminNavigationGroupDefinition;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sdilene navigation skupiny builtin a content modulu
 */
final class SystemModuleServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Urcuje registry potrebne pro skupiny systemove navigace
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zapisuje definice sdilenych navigation skupin
     */
    public function register(ContainerBuilderInterface $container): void {}

    /**
     * Registruje navigation skupiny pouzivane systemovymi a obsahovymi moduly po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminNavigationGroupRegistry::class)->register(
            new AdminNavigationGroupDefinition('system', 'admin.navigation.system', 50, AdminIcon::Gear),
        );
        $container->get(AdminNavigationGroupRegistry::class)->register(
            new AdminNavigationGroupDefinition('content', 'admin.navigation.content', 40, AdminIcon::JournalText),
        );
    }
}
