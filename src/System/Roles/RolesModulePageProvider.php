<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\DataGrid\DataGridPrimaryAction;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Page\Contract\ModuleEditorPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Roles\DataGrid\RolesDataGrid;
use Lemonade\Admin\System\Roles\Editor\RolesAdminEditorDefinitionFactory;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada index a full-page editor pro presentation modulu roli
 */
final class RolesModulePageProvider implements ModuleIndexPageProviderInterface, ModuleEditorPageProviderInterface
{
    /**
     * Nastavuje presentation zavislosti stranek roli
     */
    public function __construct(
        private readonly RolesDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationService $authorization,
        private readonly RolesAdminEditorDefinitionFactory $adminEditorDefinitions,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Urci permission potrebne pro otevreni indexu roli
     */
    public function indexPermission(): string
    {
        return 'system.roles.view';
    }

    /**
     * Sestavi DataGrid stranku s create akci, jejiz viditelnost nenahrazuje action authorization
     */
    public function index(string $locale): ModulePage
    {
        $action = $this->authorization->hasPermission('system.roles.create')
            ? new DataGridPrimaryAction(
                translationKey: 'roles.actions.create',
                icon: AdminIcon::PlusLg,
                href: $this->urls->route(name: 'admin.system.module.create', params: ['module' => 'roles']),
            )
            : null;
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'roles',
            title: $this->translator->get('roles.list.title'),
            description: $this->translator->get('roles.list.description'),
            endpoint: $this->urls->route(name: 'admin.api.datagrid.index', params: ['module' => 'roles']),
            definition: $this->grid->dataGridDefinition(),
            primaryAction: $action,
            loadingText: $this->translator->get('roles.list.loading'),
            emptyText: $this->translator->get('roles.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
        );

        return new ModulePage(
            view: 'admin::datagrid.index',
            title: $viewModel->title(),
            data: ['dataGrid' => $viewModel, 'locale' => $locale],
        );
    }

    /**
     * Sestavi create variantu full-page editoru role
     *
     * @param array<string,string> $errors
     * @param array<string,mixed> $input
     * @param array<string,mixed> $query
     */
    public function create(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
    {
        return $this->form(editor: $editor, errors: $errors, input: $input, locale: $locale, mode: 'create');
    }

    /**
     * Sestavi edit variantu full-page editoru role
     *
     * @param array<string,string> $errors
     * @param array<string,mixed> $input
     * @param array<string,mixed> $query
     */
    public function editor(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
    {
        return $this->form(editor: $editor, errors: $errors, input: $input, locale: $locale, mode: 'edit');
    }

    /**
     * Sestavi editorovou stranku z nactenych dat a presentation flags
     *
     * @param array<string,string> $errors
     * @param array<string,mixed> $input
     */
    private function form(EditorLoaded $editor, array $errors, array $input, string $locale, string $mode): ModulePage
    {
        $titleKey = $mode === 'create' ? 'roles.editor.create_title' : 'roles.editor.title';
        $editorData = $editor->data();
        $role = $editorData['role'];
        $rootProtected = ($editorData['rootProtected'] ?? false) === true;

        return new ModulePage(
            view: 'roles::editor',
            title: $this->translator->get($titleKey),
            data: [
                'adminEditor' => $this->adminEditorDefinitions->create(
                    role: $role,
                    permissionGroups: $editorData['permissionGroups'],
                    rootProtected: $rootProtected,
                    mode: $mode,
                ),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: $role,
                    oldInput: $input,
                    errors: $errors,
                    mode: $mode,
                    uiFlags: ['rootProtected' => $rootProtected],
                ),
                'locale' => $locale,
            ],
        );
    }
}
