<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Languages\Actions\LanguagesActionRegistrar;
use Lemonade\Admin\System\Languages\Actions\LanguagesSetDefaultAction;
use Lemonade\Admin\System\Languages\Actions\LanguagesSetEnabledAction;
use Lemonade\Admin\System\Languages\Audit\LanguagesAuditPresentationRegistrar;
use Lemonade\Admin\System\Languages\DataGrid\LanguagesDataGrid;
use Lemonade\Admin\System\Languages\Editor\LanguagesAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Languages\Editor\LanguagesEditor;
use Lemonade\Admin\System\Languages\Editor\LanguagesEditorValidationSchema;
use Lemonade\Admin\System\Languages\Migrations\RegisterLanguagesModule;
use Lemonade\Admin\System\Languages\Models\LanguageModel;
use Lemonade\Admin\System\Languages\Services\LanguageService;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje Languages capability vcetne editoru, akci a DataGridu
 */
final class LanguagesModuleProvider implements ServiceProviderInterface
{
    /**
     * Zapojuje runtime sluzby a registruje modulove prispevky pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new LanguagesModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.languages');
        $container->get(ViewResourceRegistry::class)->register('languages', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('languages');
        $container->singleton(LanguageModel::class, LanguageModel::class);
        $container->singleton(LanguageService::class, LanguageService::class);
        $container->singleton(LanguagesDataGrid::class, LanguagesDataGrid::class);
        $container->singleton(LanguagesEditorValidationSchema::class, LanguagesEditorValidationSchema::class);
        $container->singleton(LanguagesEditor::class, LanguagesEditor::class);
        $container->singleton(LanguagesAdminEditorDefinitionFactory::class, LanguagesAdminEditorDefinitionFactory::class);
        $container->singleton(LanguagesModulePageProvider::class, LanguagesModulePageProvider::class);
        $container->singleton(LanguagesSetDefaultAction::class, LanguagesSetDefaultAction::class);
        $container->singleton('languages.enable.action', static fn(ContainerInterface $container): LanguagesSetEnabledAction => new LanguagesSetEnabledAction($container->get(LanguageService::class), true));
        $container->singleton('languages.disable.action', static fn(ContainerInterface $container): LanguagesSetEnabledAction => new LanguagesSetEnabledAction($container->get(LanguageService::class), false));
        $container->singleton(LanguagesActionRegistrar::class, static fn(ContainerInterface $container): LanguagesActionRegistrar => new LanguagesActionRegistrar(
            $container->get(ModuleActionRegistry::class),
            $container->get(EditorDispatcher::class),
            $container->get('languages.enable.action'),
            $container->get('languages.disable.action'),
            $container->get(LanguagesSetDefaultAction::class),
        ));
        $container->singleton(LanguagesAuditPresentationRegistrar::class, LanguagesAuditPresentationRegistrar::class);
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(LanguagesDataGrid::class));
        $container->get(EditorRegistry::class)->register($definition->code(), $container->get(LanguagesEditor::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(LanguagesModulePageProvider::class));
        $container->get(ModulePageRegistry::class)->registerModalEditor($definition->code(), $container->get(LanguagesModulePageProvider::class));
        $container->get(LanguagesActionRegistrar::class)->register();
        $container->get(LanguagesAuditPresentationRegistrar::class)->register();
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(MigrationRegistry::class)->register(RegisterLanguagesModule::class);
    }
}
