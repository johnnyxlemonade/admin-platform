<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\DataGrid\DataGridPrimaryAction;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Editor\Lock\EditorLockConflictMessage;
use Lemonade\Admin\Editor\Lock\EditorLockConflictMessageProviderInterface;
use Lemonade\Admin\Editor\Lock\EditorLockOwner;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Page\Contract\ModuleEditorPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\Presentation\AdminAvatarInitials;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\Editor\UsersAdminEditorDefinitionFactory;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada index a full-page editor Users capability jako presentation nad shared transportem
 */
final class UsersModulePageProvider implements ModuleIndexPageProviderInterface, ModuleEditorPageProviderInterface, EditorLockConflictMessageProviderInterface
{
    /**
     * Nastavuje grid, editorovou factory a presentation zavislosti modulu
     */
    public function __construct(
        private readonly UsersDataGrid $grid,
        private readonly AuthorizationService $authorization,
        private readonly TranslatorInterface $translator,
        private readonly UsersAdminEditorDefinitionFactory $adminEditorDefinitions,
        private readonly UrlGenerator $urls,
        private readonly AdminFileUploadComponent $files,
    ) {}

    /**
     * Urci permission potrebne pro pristup na index uzivatelu
     */
    public function indexPermission(): string
    {
        return 'system.users.view';
    }

    /**
     * Sestavi gridovou index stranku s create akci, jejiz viditelnost nenahrazuje authorization
     */
    public function index(string $locale): ModulePage
    {
        $definition = $this->grid->dataGridDefinition();
        $action = $this->authorization->hasPermission('system.users.create')
            ? new DataGridPrimaryAction(
                translationKey: 'users.actions.create',
                icon: AdminIcon::PlusLg,
                href: $this->urls->route(name: 'admin.system.module.create', params: ['module' => 'users']),
            )
            : null;
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'users',
            title: $this->translator->get('users.list.title'),
            description: $this->translator->get('users.list.description'),
            endpoint: $this->urls->route(name: 'admin.api.datagrid.index', params: ['module' => 'users']),
            definition: $definition,
            primaryAction: $action,
            loadingText: $this->translator->get('users.list.loading'),
            emptyText: $this->translator->get('users.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
            gridClass: 'lm-datagrid--profile-list',
        );

        return new ModulePage(
            view: 'admin::datagrid.index',
            title: $viewModel->title(),
            data: ['dataGrid' => $viewModel, 'locale' => $locale],
        );
    }

    /**
     * Sestavi full-page editor pro zalozeni lokalniho uzivatele
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param array<string, mixed> $query
     */
    public function create(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
    {
        return $this->editorPage(editor: $editor, errors: $errors, input: $input, locale: $locale, mode: 'create');
    }

    /**
     * Sestavi full-page editor pro zmenu existujiciho uzivatele
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param array<string, mixed> $query
     */
    public function editor(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
    {
        return $this->editorPage(editor: $editor, errors: $errors, input: $input, locale: $locale, mode: 'edit');
    }

    /**
     * Vytvari self-profile zpravu pri konfliktu editor locku stejneho uzivatele
     */
    public function editorOpenLockConflictMessage(int $entityId, EditorLockOwner $owner): ?EditorLockConflictMessage
    {
        if ($entityId !== $owner->userId) {
            return null;
        }

        return new EditorLockConflictMessage(
            key: 'users.editor.profile_locked_by',
            params: ['name' => $owner->displayName],
        );
    }

    /**
     * Sestavi renderer context a AdminEditor definici pro create nebo update stranku
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function editorPage(EditorLoaded $editor, array $errors, array $input, string $locale, string $mode): ModulePage
    {
        $titleKey = $mode === 'create' ? 'users.editor.create_title' : 'users.editor.title';
        $editorData = $editor->data();
        $roleSource = $editor->definition()->field('role')?->optionSource();
        $roleOptions = [];
        if ($roleSource instanceof StaticSelectOptionSource) {
            foreach ($roleSource->options() as $option) {
                $roleOptions[$option->value()] = $option->label();
            }
        }
        $user = $editorData['user'];

        return new ModulePage(
            view: 'users::editor',
            title: $this->translator->get($titleKey),
            data: [
                'adminEditor' => $this->adminEditorDefinitions->create(
                    user: $user,
                    roleOptions: $roleOptions,
                    permissionGroups: $editorData['permissionGroups'],
                    roleEditable: ($editorData['roleEditable'] ?? false) === true,
                    activeEditable: ($editorData['activeEditable'] ?? false) === true,
                    activeInput: $input['active'] ?? null,
                    hasActiveInput: array_key_exists('active', $input),
                    permissionEditable: ($editorData['permissionEditable'] ?? false) === true,
                    hasProtectedAuthority: ($editorData['hasProtectedAuthority'] ?? false) === true,
                    hasExternalIdentity: (int) ($user['has_external_identity'] ?? 0) === 1,
                    mode: $mode,
                    avatarUpload: $mode === 'edit'
                        ? $this->files->collection(
                            module: 'system.users',
                            entityId: (int) $user['id'],
                            usage: 'thumbnail',
                            labelKey: 'users.fields.avatar',
                            helpKey: 'users.editor.avatar_help',
                            fallback: AdminAvatarInitials::fromValues(
                                (string) $user['first_name'],
                                (string) $user['last_name'],
                                null,
                                (string) $user['email'],
                            ),
                        )
                        : null,
                ),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: $user + ['role' => $editorData['roleId']],
                    oldInput: $input,
                    errors: $errors,
                    mode: $mode,
                ),
                'locale' => $locale,
            ],
        );
    }
}
