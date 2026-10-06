<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications;

use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\DataGrid\DataGridPrimaryAction;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Notifications\DataGrid\NotificationsDataGrid;
use Lemonade\Admin\System\Notifications\Editor\NotificationsAdminEditorDefinitionFactory;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada management index, editorove stranky a jejich audience presentation
 */
final class NotificationsModulePageProvider implements ModuleIndexPageProviderInterface, ModuleModalEditorPageProviderInterface
{
    /**
     * Nastavuje management DataGrid, audience data a definice modalniho editoru
     */
    public function __construct(
        private readonly NotificationsDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly CurrentUserProvider $current,
        private readonly NotificationModel $model,
        private readonly AuthorizationDelegationPolicy $delegation,
        private readonly UrlGenerator $urls,
        private readonly NotificationsAdminEditorDefinitionFactory $adminEditorDefinitions,
        private readonly AuthorizationService $authorization,
    ) {}

    public function indexPermission(): string
    {
        return 'system.notifications.view';
    }

    /**
     * Pridava publish action jen pro aktera s prislusnym management opravnenim
     */
    public function index(string $locale): ModulePage
    {
        $action = $this->authorization->hasPermission('system.notifications.publish')
            ? new DataGridPrimaryAction(
                translationKey: 'notifications.actions.publish',
                icon: AdminIcon::PlusLg,
                href: '#',
                modalUrl: $this->urls->route(name: 'admin.api.modal.create', params: ['module' => 'notifications']),
                modalSize: 'large',
            )
            : null;
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'notifications',
            title: $this->translator->get('notifications.list.title'),
            description: $this->translator->get('notifications.list.description'),
            endpoint: $this->urls->route(name: 'admin.api.datagrid.index', params: ['module' => 'notifications']),
            definition: $this->grid->dataGridDefinition(),
            primaryAction: $action,
            loadingText: $this->translator->get('notifications.list.loading'),
            emptyText: $this->translator->get('notifications.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
        );

        return new ModulePage(
            view: 'admin::datagrid.index',
            title: $viewModel->title(),
            data: ['dataGrid' => $viewModel, 'locale' => $locale],
        );
    }

    /**
     * Sklada modalni definici s rolemi delegovatelnymi aktualnimu aktorovi
     */
    public function modalDefinition(EditorLoaded $editor, bool $editing): AdminEditorDefinition
    {
        $notification = $editor->data()['notification'];
        /**
         * @var array<string, mixed> $notification
         */

        return $this->adminEditorDefinitions->modal(
            notification: $notification,
            roles: $this->roles(),
            input: [],
            editing: $editing,
        );
    }

    /**
     * Sklada modalni create presentation management oznameni
     */
    public function modalCreate(EditorLoaded $editor, string $locale): ModulePage
    {
        return $this->modalPage($editor, $locale, false);
    }

    /**
     * Sklada modalni edit presentation management oznameni
     */
    public function modalEdit(EditorLoaded $editor, string $locale): ModulePage
    {
        return $this->modalPage($editor, $locale, true);
    }

    /**
     * Sklada data view modalniho editoru podle create nebo edit rezimu
     */
    private function modalPage(EditorLoaded $editor, string $locale, bool $editing): ModulePage
    {
        $titleKey = $editing ? 'notifications.editor.modal_edit_title' : 'notifications.editor.modal_create_title';
        $descriptionKey = $editing ? 'notifications.editor.modal_edit_description' : 'notifications.editor.modal_create_description';

        return new ModulePage(
            view: 'notifications::modal-editor',
            title: $this->translator->get($titleKey),
            data: [
                'title' => $this->translator->get($titleKey),
                'titleKey' => $titleKey,
                'description' => $this->translator->get($descriptionKey),
                'descriptionKey' => $descriptionKey,
                'submitKey' => $editing ? 'admin.common.save' : 'notifications.actions.publish',
                'adminEditor' => $this->modalDefinition($editor, $editing),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: $editor->data()['notification'],
                    oldInput: [],
                    errors: [],
                    mode: $editing ? 'edit' : 'create',
                ),
                'locale' => $locale,
            ],
        );
    }

    /**
     * Omezuje aktivni role na cilove role povolene delegacni politikou
     *
     * @return list<array{id:int,code:string,name:string,is_super_admin:int}>
     */
    private function roles(): array
    {
        $actor = $this->current->currentUser();
        if ($actor === null) {
            return [];
        }

        $roles = $this->model->allActiveRoles();
        $assignable = array_fill_keys($this->delegation->assignableLoadedRoleIds($actor, $roles), true);

        return array_values(array_filter(
            $roles,
            static fn(array $role): bool => isset($assignable[(int) $role['id']]),
        ));
    }
}
