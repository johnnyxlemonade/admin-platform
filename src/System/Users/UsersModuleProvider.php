<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Presentation\AdminFileUploadPresentation;
use Lemonade\Admin\Presentation\AdminFileUsageDefinition;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\System\Users\Actions\UsersActionRegistrar;
use Lemonade\Admin\System\Users\Audit\UsersAuditPresentationRegistrar;
use Lemonade\Admin\System\Users\Dashboard\UsersDashboardWidgetProvider;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\Editor\UsersEditor;
use Lemonade\Admin\System\Users\Migrations\RegisterUsersModule;
use Lemonade\Admin\System\Users\Validation\PermissionOverridesRule;
use Lemonade\Admin\System\Users\Validation\RoleAssignmentsRule;
use Lemonade\Admin\System\Users\Validation\UniqueUserEmailRule;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje runtime contributions Users capability do shared Admin registry
 */
final class UsersModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Pripoji explicitne definovany dashboardovy contribution pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->transient(UsersDashboardWidgetProvider::class, UsersDashboardWidgetProvider::class);
        $container->tag(UsersDashboardWidgetProvider::class, DashboardWidgetProviderInterface::class);
    }

    /**
     * Pripoji Users runtime resources a registry contributions po finalizaci definic
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new UsersModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.users');
        $container->get(ViewResourceRegistry::class)->register('users', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('users');
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(UsersDataGrid::class));
        $container->get(EditorRegistry::class)->register($definition->code(), $container->get(UsersEditor::class));
        $container->get(ModulePageRegistry::class)->registerIndexFactory(
            $definition->code(),
            static fn(): UsersModulePageProvider => $container->get(UsersModulePageProvider::class),
        );
        $container->get(ModulePageRegistry::class)->registerEditorFactory(
            $definition->code(),
            static fn(): UsersModulePageProvider => $container->get(UsersModulePageProvider::class),
        );
        $container->get(UsersActionRegistrar::class)->register();
        $container->get(UsersAuditPresentationRegistrar::class)->register();
        $container->get(RuleRegistry::class)->addRule('system_users_unique_email', UniqueUserEmailRule::class);
        $container->get(RuleRegistry::class)->addRule('system_users_role', RoleAssignmentsRule::class);
        $container->get(RuleRegistry::class)->addRule('system_users_permission_overrides', PermissionOverridesRule::class);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(AdminFileUsageRegistry::class)->register(new AdminFileUsageDefinition('system.users', 'thumbnail', 'image', 'admin-image', false, false, AdminFileUploadPresentation::Avatar));
        $container->get(MigrationRegistry::class)->register(RegisterUsersModule::class);
    }
}
