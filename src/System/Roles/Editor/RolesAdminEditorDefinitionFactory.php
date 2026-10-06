<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Editor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorActionDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorSaveBarDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada full-page AdminEditor pro mutaci role a jejiho permission setu
 */
final class RolesAdminEditorDefinitionFactory
{
    /**
     * Nastavuje generator canonical Admin routes
     */
    public function __construct(private readonly UrlGenerator $urls) {}

    /**
     * Vytvari definici editoru, kde rootProtected omezuje pouze presentation
     *
     * @param array<string,mixed> $role
     * @param list<array<string, mixed>> $permissionGroups
     */
    public function create(array $role, array $permissionGroups, bool $rootProtected, string $mode): AdminEditorDefinition
    {
        $isCreate = $mode === 'create';
        $titleKey = $isCreate ? 'roles.editor.create_title' : 'roles.editor.title';
        $descriptionKey = $isCreate ? 'roles.editor.create_description' : 'roles.editor.description';
        $indexUrl = $this->urls->route(name: 'admin.system.module.index', params: ['module' => 'roles']);
        $formAction = $isCreate
            ? $this->urls->route(name: 'admin.system.module.create', params: ['module' => 'roles'])
            : $this->urls->route(name: 'admin.system.module.edit', params: ['module' => 'roles', 'id' => (int) $role['id']]);
        $actionUrl = $isCreate
            ? $this->urls->route(name: 'admin.system.module.ajax.create', params: ['module' => 'roles'])
            : $this->urls->route(name: 'admin.system.module.ajax.entity', params: ['module' => 'roles', 'id' => (int) $role['id']]);

        $actions = [new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.back', href: $indexUrl)];
        if (!$rootProtected) {
            $actions[] = new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.save', submit: true, primary: true);
        }

        $code = $isCreate
            ? AdminEditorFieldDefinition::text(
                name: 'code',
                labelKey: 'roles.fields.code',
            )
                ->required()
            : AdminEditorFieldDefinition::readonlyDisplay(
                name: 'code',
                labelKey: 'roles.fields.code',
            )
                ->asCode()
                ->help('', 'roles.editor.code_read_only');

        $builder = AdminEditorBuilder::create('system.roles')
            ->form(new AdminEditorFormDefinition(
                id: 'roles-editor-form',
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
                    ['label' => '', 'labelKey' => 'roles.module.name', 'href' => $indexUrl],
                    ['label' => '', 'labelKey' => $titleKey, 'current' => true],
                ],
                actions: $actions,
            ))
            ->tab(new AdminEditorTab(
                id: 'basic',
                label: '',
                blocks: [
                    new SectionBlock(
                        id: 'details',
                        blocks: [
                            new FieldGroupBlock([
                                new FieldColumn(
                                    field: $code,
                                    md: 4,
                                ),
                                new FieldColumn(
                                    field: AdminEditorFieldDefinition::text(
                                        name: 'name',
                                        labelKey: 'roles.fields.name',
                                    )
                                            ->required()
                                            ->readonly($rootProtected),
                                    md: 8,
                                ),
                            ]),
                            AdminEditorFieldDefinition::textarea(
                                name: 'description',
                                labelKey: 'roles.fields.description',
                            )
                                    ->attributes(['rows' => '4'])
                                    ->readonly($rootProtected),
                        ],
                        title: '',
                        titleKey: 'roles.editor.sections.details',
                    ),
                ],
                labelKey: 'roles.editor.tabs.basic',
                default: true,
            ))
            ->tab(new AdminEditorTab(
                id: 'permissions',
                label: '',
                blocks: [
                    new SectionBlock(
                        id: 'permissions',
                        blocks: [
                            new CustomViewBlock(
                                view: 'roles::editor.permissions',
                                context: [
                                    'groups' => $permissionGroups,
                                    'selectedPermissions' => $role['permissions'],
                                    'disabled' => $rootProtected,
                                    'rootProtected' => $rootProtected,
                                ],
                            ),
                        ],
                        title: '',
                        titleKey: 'roles.editor.sections.permissions',
                        description: '',
                        descriptionKey: 'roles.editor.permissions_help',
                    ),
                ],
                labelKey: 'roles.editor.tabs.permissions',
            ));

        if (!$rootProtected) {
            $builder->saveBar(new AdminEditorSaveBarDefinition(
                primaryAction: new AdminEditorActionDefinition(
                    label: '',
                    labelKey: 'admin.common.save_changes',
                    submit: true,
                    primary: true,
                ),
                secondaryAction: new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.discard'),
                titleKey: 'admin.editor.unsaved_changes',
                descriptionKey: 'admin.editor.unsaved_changes_help',
            ));
        }

        return $builder->build();
    }
}
