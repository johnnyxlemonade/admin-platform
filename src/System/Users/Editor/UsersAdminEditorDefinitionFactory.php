<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Editor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorActionDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorSaveBarDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorSidebarDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use Lemonade\Admin\Presentation\AdminFileUploadCollection;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada full-page AdminEditor pro profil, role assignment a permission projection uzivatele
 */
final class UsersAdminEditorDefinitionFactory
{
    /**
     * Nastavuje generator named routes pro editorove formulare
     */
    public function __construct(private readonly UrlGenerator $urls) {}

    /**
     * Vytvari editorovou definici, kde readonly a editacni flags jsou pouze presentation
     *
     * @param array<string, mixed> $user
     * @param array<int|string, string> $roleOptions
     * @param list<array<string, mixed>> $permissionGroups
     */
    public function create(
        array $user,
        array $roleOptions,
        array $permissionGroups,
        bool $roleEditable,
        bool $activeEditable,
        mixed $activeInput,
        bool $hasActiveInput,
        bool $permissionEditable,
        bool $hasProtectedAuthority,
        string $mode,
        bool $hasExternalIdentity = false,
        ?AdminFileUploadCollection $avatarUpload = null,
    ): AdminEditorDefinition {
        $isCreate = $mode === 'create';
        $activeValue = $activeEditable && $hasActiveInput && (string) $activeInput === '1'
            ? '1'
            : ((int) ($user['active'] ?? 0) === 1 ? '1' : '0');
        $titleKey = $isCreate ? 'users.editor.create_title' : 'users.editor.title';
        $descriptionKey = $isCreate ? 'users.editor.create_description' : 'users.editor.description';
        $indexUrl = $this->urls->route(name: 'admin.system.module.index', params: ['module' => 'users']);
        $formAction = $isCreate
            ? $this->urls->route(name: 'admin.system.module.create', params: ['module' => 'users'])
            : $this->urls->route(name: 'admin.system.module.edit', params: ['module' => 'users', 'id' => (int) $user['id']]);
        $actionUrl = $isCreate
            ? $this->urls->route(name: 'admin.system.module.ajax.create', params: ['module' => 'users'])
            : $this->urls->route(name: 'admin.system.module.ajax.entity', params: ['module' => 'users', 'id' => (int) $user['id']]);
        $headerMetadata = [];

        $roleFields = [];
        if ($isCreate || $roleEditable) {
            $roleFields[] = AdminEditorFieldDefinition::select(
                name: 'role',
                labelKey: 'users.fields.role',
            )
                    ->required()
                    ->options(['' => ''] + $roleOptions)
                    ->optionLabelKeys(['' => 'users.editor.role_placeholder'])
                    ->attributes([
                        'data-lemonade-permission-role' => true,
                        'data-lemonade-option-source' => 'static',
                    ]);
        }
        $roleFields[] = new CustomViewBlock(
            view: 'users::editor.access-info',
            context: ['hasProtectedAuthority' => $hasProtectedAuthority],
        );

        $profileBlocks = [
            ...($hasExternalIdentity ? [new CustomViewBlock(
                view: 'users::editor.external-profile-info',
                context: [],
            )] : []),
            new FieldGroupBlock([
                new FieldColumn(
                    field: AdminEditorFieldDefinition::text(
                        name: 'first_name',
                        labelKey: 'users.fields.first_name',
                    )->required()->readonly($hasExternalIdentity)->autocomplete($hasExternalIdentity ? 'off' : null),
                    md: 6,
                ),
                new FieldColumn(
                    field: AdminEditorFieldDefinition::text(
                        name: 'last_name',
                        labelKey: 'users.fields.last_name',
                    )->required()->readonly($hasExternalIdentity)->autocomplete($hasExternalIdentity ? 'off' : null),
                    md: 6,
                ),
                new FieldColumn(
                    field: AdminEditorFieldDefinition::email(
                        name: 'email',
                        labelKey: 'users.fields.email',
                    )->required()->readonly($hasExternalIdentity)->autocomplete($hasExternalIdentity ? 'off' : 'username'),
                    md: 6,
                ),
                new FieldColumn(
                    field: AdminEditorFieldDefinition::tel(
                        name: 'phone',
                        labelKey: 'users.fields.phone',
                    ),
                    md: 6,
                ),
            ]),
            new CustomViewBlock(
                view: 'users::editor.active',
                context: [
                    'activeEditable' => $activeEditable,
                    'activeValue' => $activeValue,
                ],
            ),
        ];
        if (!$isCreate) {
            array_unshift($profileBlocks, AdminEditorFieldDefinition::hidden('version')
                ->attributes([
                    'data-lemonade-editor-version' => true,
                    'data-lemonade-editor-version-value' => (string) ((int) ($user['version'] ?? 1)),
                ]));
        }

        $basicBlocks = [
            new SectionBlock(
                id: 'profile',
                blocks: $profileBlocks,
                title: '',
                titleKey: 'users.editor.sections.profile',
            ),
            new SectionBlock(
                id: 'access',
                blocks: $roleFields,
                title: '',
                titleKey: 'users.editor.sections.access',
                description: '',
                descriptionKey: 'users.editor.roles_help',
            ),
        ];
        if (!$hasExternalIdentity) {
            $basicBlocks[] = new SectionBlock(
                id: 'security',
                blocks: [
                    new CustomViewBlock(
                        view: 'users::editor.password',
                        context: ['isCreate' => $isCreate],
                    ),
                ],
                title: '',
                titleKey: 'users.editor.sections.security',
            );
        }

        $builder = AdminEditorBuilder::create('system.users')
            ->form(new AdminEditorFormDefinition(
                id: 'users-editor-form',
                action: $formAction,
                actionUrl: $actionUrl,
                actionKey: $isCreate ? 'create' : 'save',
                novalidate: true,
                navigateToEditAfterCreate: $isCreate,
            ))
            ->header(new AdminEditorHeaderDefinition(
                title: '',
                titleKey: $titleKey,
                description: '',
                descriptionKey: $descriptionKey,
                breadcrumbs: [
                    ['label' => '', 'labelKey' => 'users.module.name', 'href' => $indexUrl],
                    ['label' => '', 'labelKey' => $titleKey, 'current' => true],
                ],
                actions: [
                    new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.back', href: $indexUrl),
                    new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.save', submit: true, primary: true),
                ],
                metadata: $headerMetadata,
            ))
            ->saveBar(new AdminEditorSaveBarDefinition(
                primaryAction: new AdminEditorActionDefinition(
                    label: '',
                    labelKey: 'admin.common.save_changes',
                    submit: true,
                    primary: true,
                ),
                secondaryAction: new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.discard'),
                titleKey: 'admin.editor.unsaved_changes',
                descriptionKey: 'admin.editor.unsaved_changes_help',
            ))
            ->tab(new AdminEditorTab(
                id: 'basic',
                label: '',
                blocks: $basicBlocks,
                labelKey: 'users.editor.tabs.basic',
                default: true,
            ));

        if (!$isCreate) {
            $builder->tab(new AdminEditorTab(
                id: 'permissions',
                label: '',
                blocks: [
                    new CustomViewBlock(
                        view: 'users::editor.permissions',
                        context: [
                            'permissionGroups' => $permissionGroups,
                            'permissionEditable' => $permissionEditable,
                            'hasProtectedAuthority' => $hasProtectedAuthority,
                            'actionUrl' => $actionUrl,
                        ],
                    ),
                ],
                labelKey: 'users.editor.tabs.permissions',
            ));
            $builder->sidebar(new AdminEditorSidebarDefinition([
                new SectionBlock(
                    id: 'summary',
                    blocks: [
                        new CustomViewBlock(
                            view: 'users::editor.summary',
                            context: [
                                'user' => $user,
                                'thumbnail' => $avatarUpload?->thumbnail(),
                                'avatarUpload' => $avatarUpload,
                            ],
                        ),
                    ],
                ),
            ]));
        }

        return $builder->build();
    }
}
