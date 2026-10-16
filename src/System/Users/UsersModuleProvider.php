<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Admin\System\Users\Actions\UsersActionRegistrar;
use Lemonade\Admin\System\Users\Dashboard\UsersDashboardWidgetProvider;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\Editor\UsersEditor;
use Lemonade\Admin\System\Users\Http\Controller\UsersProfileController;
use Lemonade\Admin\System\Users\Migrations\RegisterUsersModule;
use Lemonade\Admin\System\Users\Routing\UsersProfileRouteRegistrar;
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
final readonly class UsersModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Pripoji explicitne definovany dashboardovy contribution pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->scoped(UsersProfileController::class, UsersProfileController::class);
        $container->singleton(UsersActionRegistrar::class, UsersActionRegistrar::class);
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
        $container->get(DataGridRegistry::class)->register($definition->code(), UsersDataGrid::class);
        $container->get(EditorRegistry::class)->register($definition->code(), UsersEditor::class);
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), UsersModulePageProvider::class);
        $container->get(ModulePageRegistry::class)->registerEditor($definition->code(), UsersModulePageProvider::class);
        $container->get(DashboardWidgetRegistry::class)->registerProvider(UsersDashboardWidgetProvider::class);
        $container->get(UsersActionRegistrar::class)->register();
        $container->get(AuditEventPresentationRegistry::class)->registerModuleDefinition($definition);
        $container->get(RuleRegistry::class)->addRule('system_users_unique_email', UniqueUserEmailRule::class);
        $container->get(RuleRegistry::class)->addRule('system_users_role', RoleAssignmentsRule::class);
        $container->get(RuleRegistry::class)->addRule('system_users_permission_overrides', PermissionOverridesRule::class);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(MigrationRegistry::class)->register(RegisterUsersModule::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(UsersProfileRouteRegistrar::class));
    }
}
