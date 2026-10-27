<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Cms\Content\Action\CmsContentAction;
use Lemonade\Admin\Cms\Content\Action\CmsContentActionType;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Cms\Content\CmsContentReadModel;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Localization\TranslationResourceRegistry;

/**
 * Registruje standardni CMS Admin capability do existujicich registru
 */
final readonly class CmsContentAdminProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Nastavuje identitu modulu a jeho konkretni Admin presentation a akce
     */
    public function __construct(
        private string $moduleCode,
        private string $translationGroup,
        private string $translationPath,
        private string $dataGridService,
        private string $pageService,
        private string $editorService,
        private string $lifecycleService,
        private string $contentDefinitionService,
    ) {}

    /**
     * Registruje sdileny create a save transport pred zmrazenim kontejneru
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definitionService = $this->contentDefinitionService;
        $readService = $this->moduleCode . '.read';
        $gridService = $this->moduleCode . '.grid_query';
        $container->scoped($readService, static fn(ContainerInterface $container): CmsContentReadModel => new CmsContentReadModel(
            $container->get(Database::class),
            $container->get($definitionService),
        ));
        $container->scoped($gridService, static fn(ContainerInterface $container): CmsContentDataGrid => new CmsContentDataGrid(
            $container->get(Database::class),
            $container->get($definitionService),
        ));
        foreach ([$this->editorService, $this->pageService, $this->dataGridService] as $consumer) {
            $container->when($consumer)->needs(CmsContentReadModel::class)->give($readService);
        }
        $container->when($this->dataGridService)->needs(CmsContentDataGrid::class)->give($gridService);
        $moduleCode = $this->moduleCode;
        $lifecycleService = $this->lifecycleService;
        $group = $this->translationGroup;
        foreach (CmsContentActionType::cases() as $type) {
            $container->scoped(
                $this->handlerId($type->value),
                static fn(ContainerInterface $container): CmsContentAction => new CmsContentAction(
                    $container->get($lifecycleService),
                    $container->get($readService),
                    $container->get(LocalActorGuard::class),
                    $container->get(AuthorizationService::class),
                    $type,
                    $moduleCode,
                    $group,
                ),
            );
        }

        $container->scoped(
            $this->handlerId('create'),
            static fn(ContainerInterface $container): StandardEditorAction => StandardEditorAction::create(
                $container->get(EditorDispatcher::class),
                $moduleCode,
            ),
        );
        $container->scoped(
            $this->handlerId('save'),
            static fn(ContainerInterface $container): StandardEditorAction => StandardEditorAction::save(
                $container->get(EditorDispatcher::class),
                $moduleCode,
            ),
        );
    }

    /**
     * Pripoji resources, grid, editor, stranky a akce do standardnich registru
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(TranslationResourceRegistry::class)->register($this->translationPath, $this->moduleCode);
        $container->get(ClientTranslationGroupRegistry::class)->register($this->translationGroup);
        $container->get(DataGridRegistry::class)->register($this->moduleCode, $this->dataGridService);
        $container->get(ModulePageRegistry::class)->registerIndex($this->moduleCode, $this->pageService);
        $container->get(ModulePageRegistry::class)->registerEditor($this->moduleCode, $this->pageService);
        $container->get(EditorRegistry::class)->register($this->moduleCode, $this->editorService);
        $this->registerActions($container->get(ModuleActionRegistry::class));
    }

    /**
     * Pripoji standardni CMS akce a jejich existujici permission hranice
     */
    public function registerActions(ModuleActionRegistry $actions): void
    {
        $module = $this->moduleCode;
        $group = $this->translationGroup;
        $actions->register($module, new ModuleActionDefinition(
            key: 'create',
            permission: $module . '.create',
            labelKey: $group . '.actions.create',
            refreshGrid: true,
        ), $this->handlerId('create'));
        $actions->register($module, new ModuleActionDefinition(
            key: 'save',
            permission: $module . '.edit_own',
            labelKey: 'admin.common.save',
        ), $this->handlerId('save'));
        $actions->register($module, new ModuleActionDefinition(
            key: 'change-url',
            permission: $module . '.edit_own',
            labelKey: 'admin.url_segment.save',
        ), $this->handlerId('change-url'));
        $actions->register($module, new ModuleActionDefinition(
            key: 'delete',
            permission: $module . '.delete_own',
            labelKey: $group . '.actions.delete',
            confirmation: new ConfirmationDefinition($group . '.confirm.delete'),
            refreshGrid: true,
        ), $this->handlerId('delete'));
        $actions->register($module, new ModuleActionDefinition(
            key: 'restore',
            permission: $module . '.restore_own',
            labelKey: $group . '.actions.restore',
            confirmation: new ConfirmationDefinition($group . '.confirm.restore'),
            refreshGrid: true,
        ), $this->handlerId('restore'));
        $actions->register($module, new ModuleActionDefinition(
            key: 'assign-owner',
            permission: $module . '.assign_owner',
            labelKey: $group . '.actions.assign_owner',
        ), $this->handlerId('assign-owner'));
    }

    /**
     * Vrati stabilni service ID sdilene editorove akce
     *
     * @return non-empty-string
     */
    private function handlerId(string $action): string
    {
        return 'admin.' . $this->moduleCode . '.action.' . $action;
    }
}
