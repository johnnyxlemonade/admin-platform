<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Roles\Actions\RolesActionRegistrar;
use Lemonade\Admin\System\Roles\DataGrid\RolesDataGrid;
use Lemonade\Admin\System\Roles\Editor\RolesEditor;
use Lemonade\Admin\System\Roles\Migrations\RegisterRolesModule;
use Lemonade\Admin\System\Roles\Validation\RolePermissionsRule;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\Validation\Rule\RuleRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje runtime contributions modulu roli do shared Admin registry
 */
final class RolesModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Pripoji sluzby, presentation registry, zdroje a migraci modulu roli
     */
    public function register(ContainerBuilderInterface $container): void {}

    /**
     * Pripoji Roles resources a registry contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new RolesModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.roles');
        $container->get(ViewResourceRegistry::class)->register('roles', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('roles');
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(RolesDataGrid::class));
        $container->get(EditorRegistry::class)->register($definition->code(), $container->get(RolesEditor::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(RolesModulePageProvider::class));
        $container->get(ModulePageRegistry::class)->registerEditor($definition->code(), $container->get(RolesModulePageProvider::class));
        $container->get(RolesActionRegistrar::class)->register();
        $container->get(AuditEventPresentationRegistry::class)->registerModuleDefinition($definition);
        $container->get(RuleRegistry::class)->addRule('system_roles_permissions', RolePermissionsRule::class);
        $container->get(MigrationRegistry::class)->register(RegisterRolesModule::class);
    }
}
