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
use Lemonade\Admin\System\Users\Actions\UsersActivateAction;
use Lemonade\Admin\System\Users\Actions\UsersDeactivateAction;
use Lemonade\Admin\System\Users\Actions\UsersDeleteAction;
use Lemonade\Admin\System\Users\Actions\UsersEditorCreateAction;
use Lemonade\Admin\System\Users\Actions\UsersEditorSaveAction;
use Lemonade\Admin\System\Users\Actions\UsersPermissionPreviewAction;
use Lemonade\Admin\System\Users\Actions\UsersRestoreAction;
use Lemonade\Admin\System\Users\Audit\UsersAuditPresentationRegistrar;
use Lemonade\Admin\System\Users\Dashboard\UsersDashboardWidgetProvider;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGridQuery;
use Lemonade\Admin\System\Users\Editor\UsersAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Users\Editor\UsersEditor;
use Lemonade\Admin\System\Users\Editor\UsersEditorValidationSchema;
use Lemonade\Admin\System\Users\Migrations\RegisterUsersModule;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Admin\System\Users\Models\UserPermissionOverrideModel;
use Lemonade\Admin\System\Users\Models\UserRoleModel;
use Lemonade\Admin\System\Users\Policies\UsersEditorAccessPolicy;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Admin\System\Users\Validation\PermissionOverridesRule;
use Lemonade\Admin\System\Users\Validation\RoleAssignmentsRule;
use Lemonade\Admin\System\Users\Validation\UniqueUserEmailRule;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje runtime contributions Users capability do shared Admin registry
 */
final class UsersModuleProvider implements ServiceProviderInterface
{
    /**
     * Pripoji Users sluzby, registry entrypointy, zdroje a validacni rules
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new UsersModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.users');
        $container->get(ViewResourceRegistry::class)->register('users', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('users');
        $container->singleton(UserModel::class, UserModel::class);
        $container->singleton(UserRoleModel::class, UserRoleModel::class);
        $container->singleton(UserPermissionOverrideModel::class, UserPermissionOverrideModel::class);
        $container->singleton(UserService::class, UserService::class);
        $container->singleton(UsersDataGridQuery::class, UsersDataGridQuery::class);
        $container->singleton(UsersDataGrid::class, UsersDataGrid::class);
        $container->singleton(UsersEditorValidationSchema::class, UsersEditorValidationSchema::class);
        $container->singleton(UsersEditorAccessPolicy::class, UsersEditorAccessPolicy::class);
        $container->singleton(UsersEditor::class, UsersEditor::class);
        $container->singleton(UsersAdminEditorDefinitionFactory::class, UsersAdminEditorDefinitionFactory::class);
        $container->singleton(UsersModulePageProvider::class, UsersModulePageProvider::class);
        $container->singletonTagged(UsersDashboardWidgetProvider::class, UsersDashboardWidgetProvider::class, DashboardWidgetProviderInterface::class);
        $container->singleton(UsersEditorSaveAction::class, UsersEditorSaveAction::class);
        $container->singleton(UsersEditorCreateAction::class, UsersEditorCreateAction::class);
        $container->singleton(UsersPermissionPreviewAction::class, UsersPermissionPreviewAction::class);
        $container->singleton(UsersActivateAction::class, UsersActivateAction::class);
        $container->singleton(UsersDeactivateAction::class, UsersDeactivateAction::class);
        $container->singleton(UsersDeleteAction::class, UsersDeleteAction::class);
        $container->singleton(UsersRestoreAction::class, UsersRestoreAction::class);
        $container->singleton(UsersActionRegistrar::class, UsersActionRegistrar::class);
        $container->singleton(UsersAuditPresentationRegistrar::class, UsersAuditPresentationRegistrar::class);
        $container->transient(UniqueUserEmailRule::class, UniqueUserEmailRule::class);
        $container->transient(RoleAssignmentsRule::class, RoleAssignmentsRule::class);
        $container->transient(PermissionOverridesRule::class, PermissionOverridesRule::class);
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
