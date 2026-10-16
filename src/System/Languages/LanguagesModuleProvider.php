<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Languages\Actions\LanguagesActionRegistrar;
use Lemonade\Admin\System\Languages\Actions\LanguagesSetEnabledAction;
use Lemonade\Admin\System\Languages\DataGrid\LanguagesDataGrid;
use Lemonade\Admin\System\Languages\Editor\LanguagesEditor;
use Lemonade\Admin\System\Languages\Migrations\RegisterLanguagesModule;
use Lemonade\Admin\System\Languages\Services\LanguageService;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje Languages capability vcetne editoru, akci a DataGridu
 */
final readonly class LanguagesModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapojuje runtime sluzby a registruje modulove prispevky pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->scoped(LanguagesActionRegistrar::SAVE_HANDLER, static fn(ContainerInterface $container): StandardEditorAction => StandardEditorAction::save($container->get(EditorDispatcher::class), 'system.languages'));
        $container->scoped(LanguagesActionRegistrar::CREATE_HANDLER, static fn(ContainerInterface $container): StandardEditorAction => StandardEditorAction::create($container->get(EditorDispatcher::class), 'system.languages'));
        $container->scoped(LanguagesActionRegistrar::ENABLE_HANDLER, static fn(ContainerInterface $container): LanguagesSetEnabledAction => new LanguagesSetEnabledAction($container->get(LanguageService::class), true));
        $container->scoped(LanguagesActionRegistrar::DISABLE_HANDLER, static fn(ContainerInterface $container): LanguagesSetEnabledAction => new LanguagesSetEnabledAction($container->get(LanguageService::class), false));
        $container->singleton(LanguagesActionRegistrar::class, LanguagesActionRegistrar::class);
    }

    /**
     * Pripoji Languages resources a registry contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new LanguagesModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.languages');
        $container->get(ViewResourceRegistry::class)->register('languages', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('languages');
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), LanguagesDataGrid::class);
        $container->get(EditorRegistry::class)->register($definition->code(), LanguagesEditor::class);
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), LanguagesModulePageProvider::class);
        $container->get(ModulePageRegistry::class)->registerModalEditor($definition->code(), LanguagesModulePageProvider::class);
        $container->get(LanguagesActionRegistrar::class)->register();
        $container->get(AuditEventPresentationRegistry::class)->registerModuleDefinition($definition);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(MigrationRegistry::class)->register(RegisterLanguagesModule::class);
    }
}
