<?php

declare(strict_types=1);

namespace Lemonade\Admin;

use Lemonade\Admin\Audit\CoreAuditEventServiceProvider;
use Lemonade\Admin\Auth\AdminAuthRuntimeServiceProvider;
use Lemonade\Admin\Auth\AdminAuthServiceProvider;
use Lemonade\Admin\Authorization\CoreAuthorizationServiceProvider;
use Lemonade\Admin\Cms\AdminCmsServiceProvider;
use Lemonade\Admin\Cms\Migrations\CreateCoreCmsRoutes;
use Lemonade\Admin\Dashboard\AdminDashboardServiceProvider;
use Lemonade\Admin\Dashboard\Audit\DashboardAuditServiceProvider;
use Lemonade\Admin\Editor\AdminEditorServiceProvider;
use Lemonade\Admin\Identity\CoreIdentityServiceProvider;
use Lemonade\Admin\Install\AdminInstallationServiceProvider;
use Lemonade\Admin\Install\DatabaseConfigurationValidator;
use Lemonade\Admin\Module\AdminModuleTransportServiceProvider;
use Lemonade\Admin\Modules\CoreModuleServiceProvider;
use Lemonade\Admin\Modules\ModuleBootstrapServiceProvider;
use Lemonade\Admin\Notification\AdminNotificationServiceProvider;
use Lemonade\Admin\Platform\Migrations\CreateCoreAuditLog;
use Lemonade\Admin\Platform\Migrations\CreateCoreAuthorizationAssignments;
use Lemonade\Admin\Platform\Migrations\CreateCoreAuthorizationCatalog;
use Lemonade\Admin\Platform\Migrations\CreateCoreEditorLocks;
use Lemonade\Admin\Platform\Migrations\CreateCoreFiles;
use Lemonade\Admin\Platform\Migrations\CreateCoreLanguages;
use Lemonade\Admin\Platform\Migrations\CreateCoreModuleCatalog;
use Lemonade\Admin\Platform\Migrations\CreateCoreModuleRoutePrefixes;
use Lemonade\Admin\Platform\Migrations\CreateCoreTranslationOverrides;
use Lemonade\Admin\Platform\Migrations\CreateCoreUserIdentities;
use Lemonade\Admin\Platform\Migrations\CreateCoreUsers;
use Lemonade\Admin\Presentation\AdminPresentationServiceProvider;
use Lemonade\Admin\System\SystemModuleServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;

/**
 * Sklada internu infrastrukturu budouciho Admin package
 */
final class AdminPackageServiceProvider implements ServiceProviderInterface
{
    /**
     * Registruje Admin capability, jejich Core podpory a manifestove moduly ve stabilnim poradi
     */
    public function register(ContainerBuilderInterface $container): void
    {
        (new CoreIdentityServiceProvider())->register($container);
        (new CoreAuditEventServiceProvider())->register($container);
        (new CoreModuleServiceProvider())->register($container);
        (new CoreAuthorizationServiceProvider())->register($container);
        $container->singleton(DatabaseConfigurationValidator::class, DatabaseConfigurationValidator::class);
        $this->registerPlatformMigrations($container->get(MigrationRegistry::class));
        (new AdminCmsServiceProvider())->register($container);
        (new AdminAuthRuntimeServiceProvider())->register($container);

        (new AdminServiceProvider())->register($container);
        (new AdminPresentationServiceProvider())->register($container);
        (new AdminModuleTransportServiceProvider())->register($container);
        (new AdminEditorServiceProvider())->register($container);
        (new AdminDashboardServiceProvider())->register($container);
        (new AdminNotificationServiceProvider())->register($container);
        (new AdminInstallationServiceProvider())->register($container);
        (new AdminAuthServiceProvider())->register($container);
        (new SystemModuleServiceProvider())->register($container);
        (new ModuleBootstrapServiceProvider())->register($container);
        (new DashboardAuditServiceProvider())->register($container);
    }

    /**
     * Registruje stabilni schema Admin platformy vcetne CMS route contractu
     */
    private function registerPlatformMigrations(MigrationRegistry $migrations): void
    {
        $migrations->register(CreateCoreLanguages::class);
        $migrations->register(CreateCoreModuleCatalog::class);
        $migrations->register(CreateCoreModuleRoutePrefixes::class);
        $migrations->register(CreateCoreUsers::class);
        $migrations->register(CreateCoreUserIdentities::class);
        $migrations->register(CreateCoreAuthorizationCatalog::class);
        $migrations->register(CreateCoreAuthorizationAssignments::class);
        $migrations->register(CreateCoreAuditLog::class);
        $migrations->register(CreateCoreEditorLocks::class);
        $migrations->register(CreateCoreFiles::class);
        $migrations->register(CreateCoreTranslationOverrides::class);
        $migrations->register(CreateCoreCmsRoutes::class);
    }
}
