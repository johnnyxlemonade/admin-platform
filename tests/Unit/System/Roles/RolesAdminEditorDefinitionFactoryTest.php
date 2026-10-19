<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Roles;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\ReadonlyDisplayField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextareaField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextInputField;
use Lemonade\Admin\Editor\AdminEditor\Layout\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\Layout\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Admin\System\Roles\Editor\RolesAdminEditorDefinitionFactory;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class RolesAdminEditorDefinitionFactoryTest extends TestCase
{
    public function testCreateDefinitionKeepsCreateTransportAndEditableRoleCode(): void
    {
        $editor = $this->factory()->create(
            role: $this->role(),
            permissionGroups: $this->permissionGroups(),
            rootProtected: false,
            mode: 'create',
        );
        $fields = $this->basicFields($editor);

        self::assertSame('/admin/system/roles/create', $editor->form()->action());
        self::assertSame('/admin/system/roles/ajax', $editor->form()->actionUrl());
        self::assertSame('create', $editor->form()->actionKey());
        self::assertTrue($editor->form()->navigateToEditAfterCreate());
        self::assertTrue($editor->form()->novalidate());
        self::assertInstanceOf(TextInputField::class, $fields['code']);
        self::assertTrue($fields['code']->isRequired());
        self::assertSame(['code' => 4, 'name' => 8], $this->basicColumnWidths($editor));
        self::assertSame(['basic', 'permissions'], array_map(static fn(AdminEditorTab $tab): string => $tab->id(), $editor->tabs()));
        self::assertSame('roles.editor.tabs.basic', $editor->tabs()[0]->labelKey());
        self::assertTrue($editor->tabs()[0]->default());
        self::assertNotNull($editor->saveBar());
        self::assertSame('admin.common.save_changes', $editor->saveBar()->primaryAction()->labelKey());
    }

    public function testEditDefinitionKeepsReadonlyCodeAndPermissionCustomBlockContext(): void
    {
        $editor = $this->factory()->create(
            role: $this->role(),
            permissionGroups: $this->permissionGroups(),
            rootProtected: false,
            mode: 'edit',
        );
        $fields = $this->basicFields($editor);
        $custom = $this->permissionsCustomBlock($editor);

        self::assertSame('/admin/system/roles/edit/7', $editor->form()->action());
        self::assertSame('/admin/system/roles/ajax/7', $editor->form()->actionUrl());
        self::assertSame('save', $editor->form()->actionKey());
        self::assertFalse($editor->form()->navigateToEditAfterCreate());
        self::assertInstanceOf(ReadonlyDisplayField::class, $fields['code']);
        self::assertTrue($fields['code']->displaysAsCode());
        self::assertSame('roles.editor.code_read_only', $fields['code']->helpTranslationKey());
        self::assertNotNull($editor->saveBar());
        self::assertSame('roles::editor.permissions', $custom->view());
        self::assertSame([
            'groups' => $this->permissionGroups(),
            'selectedPermissions' => ['system.roles.view'],
            'disabled' => false,
            'rootProtected' => false,
        ], $custom->context());
    }

    public function testRootProtectedDefinitionKeepsFieldsReadonlyAndOmitsSaveAction(): void
    {
        $editor = $this->factory()->create(
            role: $this->role(),
            permissionGroups: $this->permissionGroups(),
            rootProtected: true,
            mode: 'edit',
        );
        $fields = $this->basicFields($editor);
        $header = $editor->header();

        self::assertInstanceOf(TextInputField::class, $fields['name']);
        self::assertInstanceOf(TextareaField::class, $fields['description']);
        self::assertTrue($fields['name']->isReadonly());
        self::assertTrue($fields['description']->isReadonly());
        self::assertNotNull($header);
        self::assertCount(1, $header->actions());
        self::assertSame('admin.common.back', $header->actions()[0]->labelKey());
        self::assertNull($editor->saveBar());
    }

    /** @return array<string, \Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition> */
    private function basicFields(AdminEditorDefinition $editor): array
    {
        $tab = $editor->tabs()[0];
        $card = $tab->blocks()[0];
        self::assertInstanceOf(SectionBlock::class, $card);

        $fields = [];
        foreach ($card->blocks() as $block) {
            if ($block instanceof \Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition) {
                $fields[$block->name()] = $block;
                continue;
            }
            self::assertInstanceOf(FieldGroupBlock::class, $block);
            foreach ($block->columns() as $column) {
                $fields[$column->field()->name()] = $column->field();
            }
        }

        return $fields;
    }

    /** @return array<string, int> */
    private function basicColumnWidths(AdminEditorDefinition $editor): array
    {
        $card = $editor->tabs()[0]->blocks()[0];
        self::assertInstanceOf(SectionBlock::class, $card);
        self::assertInstanceOf(FieldGroupBlock::class, $card->blocks()[0]);

        $widths = [];
        foreach ($card->blocks()[0]->columns() as $column) {
            $span = $column->md();
            self::assertNotNull($span);
            $widths[$column->field()->name()] = $span;
        }
        return $widths;
    }

    private function permissionsCustomBlock(AdminEditorDefinition $editor): CustomViewBlock
    {
        $tab = $editor->tabs()[1];
        $card = $tab->blocks()[0];
        self::assertInstanceOf(SectionBlock::class, $card);
        $custom = $card->blocks()[0];
        self::assertInstanceOf(CustomViewBlock::class, $custom);

        return $custom;
    }

    /** @return array<string, mixed> */
    private function role(): array
    {
        return [
            'id' => 7,
            'code' => 'editor',
            'name' => 'Editor',
            'description' => 'Publishes content.',
            'permissions' => ['system.roles.view'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function permissionGroups(): array
    {
        return [[
            'moduleCode' => 'system.roles',
            'label' => 'Roles',
            'labelKey' => 'roles.module.name',
            'icon' => 'bi bi-people',
            'permissions' => [[
                'code' => 'system.roles.view',
                'label' => 'View roles',
                'labelKey' => 'roles.permissions.view',
                'requires' => [],
            ]],
        ]];
    }

    private function factory(): RolesAdminEditorDefinitionFactory
    {
        $router = new Router();
        $router->getNamed('admin.system.module.index', '/admin/system/{module}', ControllerAction::for('TestController', 'index'));
        $router->getNamed('admin.system.module.create', '/admin/system/{module}/create', ControllerAction::for('TestController', 'create'));
        $router->getNamed('admin.system.module.edit', '/admin/system/{module}/edit/{id}', ControllerAction::for('TestController', 'edit'));
        $router->postNamed('admin.system.module.ajax.create', '/admin/system/{module}/ajax', ControllerAction::for('TestController', 'create'));
        $router->postNamed('admin.system.module.ajax.entity', '/admin/system/{module}/ajax/{id}', ControllerAction::for('TestController', 'entity'));

        return new RolesAdminEditorDefinitionFactory(urls: new UrlGenerator(router: $router));
    }
}
