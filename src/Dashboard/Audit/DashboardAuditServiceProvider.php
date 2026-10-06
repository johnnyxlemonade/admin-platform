<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Audit;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sluzby auditni casti dashboardu
 */
final class DashboardAuditServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
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
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(DashboardAuditPresentationRegistrar::class, DashboardAuditPresentationRegistrar::class);
        $container->get(DashboardAuditPresentationRegistrar::class)->register();
    }
}
