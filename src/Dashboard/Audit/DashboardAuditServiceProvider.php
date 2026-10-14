<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Audit;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sluzby auditni casti dashboardu
 */
final readonly class DashboardAuditServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Urcuje registry potrebne pro dashboardove auditni presentation
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zaregistruje auditni zobrazeni dashboardu
     */
    public function register(ContainerBuilderInterface $container): void {}

    public function boot(ContainerInterface $container): void
    {
        $container->get(DashboardAuditPresentationRegistrar::class)->register();
    }
}
