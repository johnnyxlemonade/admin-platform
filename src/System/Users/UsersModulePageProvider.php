<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\DataGrid\DataGridPrimaryAction;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
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
 * Builds Users index, management editor and self-profile pages over shared transports
 */
final readonly class UsersModulePageProvider implements ModuleIndexPageProviderInterface, ModuleEditorPageProviderInterface, EditorLockConflictMessageProviderInterface
{
    /**
     * Creates the provider with grid, editor, and presentation dependencies
     */
    public function __construct(
        private UsersDataGrid $grid,
        private AuthorizationService $authorization,
        private TranslatorInterface $translator,
        private UsersAdminEditorDefinitionFactory $adminEditorDefinitions,
        private UrlGenerator $urls,
        private AdminFileUploadComponent $files,
    ) {}

    /**
     * Returns the permission required to access the Users index
     */
    public function indexPermission(): string
    {
        return 'system.users.view';
    }

    /**
     * Builds the Users grid page while leaving authorization to the server boundary
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
     * Builds the full-page editor for creating a local user
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
     * Builds the full-page editor for changing an existing user
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
     * Builds the self-service profile independently of the Users management index
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $activity
     * @param array<string, string> $passwordErrors
     */
    public function profile(
        EditorLoaded $editor,
        array $errors,
        array $input,
        string $locale,
        string $section,
        array $activity,
        ?string $profileUpdatedAt,
        ?string $passwordChangedAt,
        ?string $mfaEnabledAt,
        array $passwordErrors = [],
        bool $passwordChanged = false,
    ): ModulePage {
        $editorData = $editor->data();
        $user = $editorData['user'];
        $avatarUpload = $this->files->collection(
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
        );
        $roleSource = $editor->definition()->field('role')?->optionSource();
        $roleOptions = [];

        if ($roleSource instanceof StaticSelectOptionSource) {
            foreach ($roleSource->options() as $option) {
                $roleOptions[$option->value()] = $option->label();
            }
        }

        return new ModulePage(
            view: 'users::profile',
            title: $this->translator->get('users.profile.title'),
            data: [
                'user' => $user,
                'section' => $section,
                'activity' => $activity,
                'profileUpdatedAt' => $profileUpdatedAt,
                'passwordChangedAt' => $passwordChangedAt,
                'mfaEnabledAt' => $mfaEnabledAt,
                'passwordErrors' => $passwordErrors,
                'passwordChanged' => $passwordChanged,
                'avatarUpload' => $avatarUpload,
                'adminEditor' => $section === 'basic' ? $this->adminEditorDefinitions->create(
                    user: $user,
                    roleOptions: $roleOptions,
                    permissionGroups: $editorData['permissionGroups'],
                    roleEditable: false,
                    activeEditable: false,
                    activeInput: $input['active'] ?? null,
                    hasActiveInput: array_key_exists('active', $input),
                    permissionEditable: false,
                    hasProtectedAuthority: false,
                    mode: 'profile',
                    hasExternalIdentity: (int) ($user['has_external_identity'] ?? 0) === 1,
                    avatarUpload: $avatarUpload,
                    isSelf: true,
                    profileContext: true,
                ) : null,
                'adminEditorContext' => $section === 'basic' ? new AdminEditorRenderContext(
                    values: $user + ['role' => $editorData['roleId']],
                    oldInput: $input,
                    errors: $errors,
                    mode: 'edit',
                ) : null,
                'locale' => $locale,
            ],
        );
    }

    /**
     * Creates a self-profile message for an editor lock held by the same user
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
     * Builds the renderer context and AdminEditor definition for management pages
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
                    mode: $mode,
                    hasExternalIdentity: (int) ($user['has_external_identity'] ?? 0) === 1,
                    avatarUpload: $mode === 'edit'
                        || $mode === 'profile'
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
                    isSelf: ($editorData['isSelf'] ?? false) === true,
                    canResetMfa: ($editorData['canResetMfa'] ?? false) === true,
                    profileContext: $mode === 'profile',
                ),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: $user + ['role' => $editorData['roleId']],
                    oldInput: $input,
                    errors: $errors,
                    mode: $mode === 'profile' ? 'edit' : $mode,
                ),
                'locale' => $locale,
            ],
        );
    }
}
