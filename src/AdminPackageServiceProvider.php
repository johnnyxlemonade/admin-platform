<?php

declare(strict_types=1);

namespace Lemonade\Admin;

use Lemonade\Admin\Audit\CoreAuditEventServiceProvider;
use Lemonade\Admin\Auth\AdminAuthRuntimeServiceProvider;
use Lemonade\Admin\Auth\AdminAuthServiceProvider;
use Lemonade\Admin\Authorization\CoreAuthorizationServiceProvider;
use Lemonade\Admin\Cms\AdminCmsServiceProvider;
use Lemonade\Admin\Dashboard\AdminDashboardServiceProvider;
use Lemonade\Admin\Dashboard\Audit\DashboardAuditServiceProvider;
use Lemonade\Admin\Editor\AdminEditorServiceProvider;
use Lemonade\Admin\Identity\CoreIdentityServiceProvider;
use Lemonade\Admin\Install\AdminInstallationServiceProvider;
use Lemonade\Admin\Module\AdminModuleTransportServiceProvider;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
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
use Lemonade\Admin\Platform\Migrations\CreateCoreTranslationOverrides;
use Lemonade\Admin\Platform\Migrations\CreateCoreUserIdentities;
use Lemonade\Admin\Platform\Migrations\CreateCoreUsers;
use Lemonade\Admin\Platform\Migrations\CreateCoreUserTotp;
use Lemonade\Admin\Platform\Migrations\CreateFrameworkRateLimitTable;
use Lemonade\Admin\Presentation\AdminPresentationServiceProvider;
use Lemonade\Admin\System\SystemModuleServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use LogicException;

/**
 * Sklada internu infrastrukturu budouciho Admin package
 */
final class AdminPackageServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /** @var list<BootableServiceProviderInterface> */
    private array $bootableProviders = [];

    private ?ModuleBootstrapServiceProvider $moduleBootstrap = null;

    /**
     * Registruje Admin capability, jejich Core podpory a manifestove moduly ve stabilnim poradi
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $this->registerProvider(new CoreIdentityServiceProvider(), $container);
        $this->registerProvider(new CoreAuditEventServiceProvider(), $container);
        $this->registerProvider(new CoreModuleServiceProvider(), $container);
        $this->registerProvider(new CoreAuthorizationServiceProvider(), $container);
        $this->registerProvider(new AdminCmsServiceProvider(), $container);
        $this->registerProvider(new AdminAuthRuntimeServiceProvider(), $container);

        $this->registerProvider(new AdminServiceProvider(), $container);
        $this->registerProvider(new AdminPresentationServiceProvider(), $container);
        $this->registerProvider(new AdminModuleTransportServiceProvider(), $container);
        $this->registerProvider(new AdminEditorServiceProvider(), $container);
        $this->registerProvider(new AdminDashboardServiceProvider(), $container);
        $this->registerProvider(new AdminNotificationServiceProvider(), $container);
        $this->registerProvider(new AdminInstallationServiceProvider(), $container);
        $this->registerProvider(new AdminAuthServiceProvider(), $container);
        $this->registerProvider(new SystemModuleServiceProvider(), $container);
        $this->moduleBootstrap = new ModuleBootstrapServiceProvider($container->get(ModuleCatalog::class));
        $this->moduleBootstrap->register($container);
        $this->bootableProviders[] = $this->moduleBootstrap;
        $this->registerProvider(new DashboardAuditServiceProvider(), $container);
    }

    /**
     * Dokonci runtime contributions manifestovych modulu po framework container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        if ($this->moduleBootstrap === null) {
            throw new LogicException('Admin package module bootstrap must register before booting.');
        }

        foreach ($this->bootableProviders as $provider) {
            $provider->boot($container);
        }

        $this->registerPlatformMigrations($container->get(MigrationRegistry::class));
    }

    /**
     * Registruje stabilni schema Admin platformy
     */
    private function registerPlatformMigrations(MigrationRegistry $migrations): void
    {
        $migrations->register(CreateCoreLanguages::class);
        $migrations->register(CreateCoreModuleCatalog::class);
        $migrations->register(CreateCoreUsers::class);
        $migrations->register(CreateCoreUserTotp::class);
        $migrations->register(CreateCoreUserIdentities::class);
        $migrations->register(CreateCoreAuthorizationCatalog::class);
        $migrations->register(CreateCoreAuthorizationAssignments::class);
        $migrations->register(CreateCoreAuditLog::class);
        $migrations->register(CreateCoreEditorLocks::class);
        $migrations->register(CreateCoreFiles::class);
        $migrations->register(CreateCoreTranslationOverrides::class);
        $migrations->register(CreateFrameworkRateLimitTable::class);
    }

    /**
     * Registruje interne skladany provider a uchova jeho boot lifecycle ve stabilnim poradi.
     */
    private function registerProvider(ServiceProviderInterface $provider, ContainerBuilderInterface $container): void
    {
        $provider->register($container);

        if ($provider instanceof BootableServiceProviderInterface) {
            $this->bootableProviders[] = $provider;
        }
    }
}
