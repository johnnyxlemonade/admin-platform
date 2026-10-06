<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Translations\Actions\TranslationsActionRegistrar;
use Lemonade\Admin\System\Translations\Actions\TranslationsBulkResetAction;
use Lemonade\Admin\System\Translations\Actions\TranslationsResetAction;
use Lemonade\Admin\System\Translations\Audit\TranslationsAuditPresentationRegistrar;
use Lemonade\Admin\System\Translations\DataGrid\TranslationsDataGrid;
use Lemonade\Admin\System\Translations\Editor\TranslationsAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Translations\Editor\TranslationsEditor;
use Lemonade\Admin\System\Translations\Migrations\RegisterTranslationsModule;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje source projection, editor a action transport modulu prekladu
 */
final class TranslationsModuleProvider implements ServiceProviderInterface
{
    /**
     * Pripojuje modulove registry a resources pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new TranslationsModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.translations');
        $container->get(ViewResourceRegistry::class)->register('translations', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('translations');
        $container->singleton(TranslationsCatalog::class, TranslationsCatalog::class);
        $container->singleton(TranslationsDataGrid::class, TranslationsDataGrid::class);
        $container->singleton(TranslationsEditor::class, TranslationsEditor::class);
        $container->singleton(TranslationsAdminEditorDefinitionFactory::class, TranslationsAdminEditorDefinitionFactory::class);
        $container->singleton(TranslationsModulePageProvider::class, TranslationsModulePageProvider::class);
        $container->singleton(TranslationsResetAction::class, TranslationsResetAction::class);
        $container->singleton(TranslationsBulkResetAction::class, TranslationsBulkResetAction::class);
        $container->singleton(TranslationsActionRegistrar::class, TranslationsActionRegistrar::class);
        $container->singleton(TranslationsAuditPresentationRegistrar::class, TranslationsAuditPresentationRegistrar::class);
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(TranslationsDataGrid::class));
        $container->get(EditorRegistry::class)->register($definition->code(), $container->get(TranslationsEditor::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(TranslationsModulePageProvider::class));
        $container->get(ModulePageRegistry::class)->registerModalEditor($definition->code(), $container->get(TranslationsModulePageProvider::class));
        $container->get(TranslationsActionRegistrar::class)->register();
        $container->get(TranslationsAuditPresentationRegistrar::class)->register();
        $container->get(MigrationRegistry::class)->register(RegisterTranslationsModule::class);
    }
}
