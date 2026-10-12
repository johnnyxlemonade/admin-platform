<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Translations\Actions\TranslationsActionRegistrar;
use Lemonade\Admin\System\Translations\DataGrid\TranslationsDataGrid;
use Lemonade\Admin\System\Translations\Editor\TranslationsEditor;
use Lemonade\Admin\System\Translations\Migrations\RegisterTranslationsModule;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje source projection, editor a action transport modulu prekladu
 */
final class TranslationsModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Pripojuje modulove registry a resources pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void {}

    /**
     * Pripoji Translations resources a registry contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new TranslationsModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.translations');
        $container->get(ViewResourceRegistry::class)->register('translations', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('translations');
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(TranslationsDataGrid::class));
        $container->get(EditorRegistry::class)->register($definition->code(), $container->get(TranslationsEditor::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(TranslationsModulePageProvider::class));
        $container->get(ModulePageRegistry::class)->registerModalEditor($definition->code(), $container->get(TranslationsModulePageProvider::class));
        $container->get(TranslationsActionRegistrar::class)->register();
        $container->get(AuditEventPresentationRegistry::class)->registerModuleDefinition($definition);
        $container->get(MigrationRegistry::class)->register(RegisterTranslationsModule::class);
    }
}
