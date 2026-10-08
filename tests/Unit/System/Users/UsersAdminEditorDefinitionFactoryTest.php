<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use Lemonade\Admin\Presentation\AdminFileUploadCollection;
use Lemonade\Admin\Presentation\AdminFileUploadPresentation;
use Lemonade\Admin\Presentation\AdminFileUploadProfilePresentation;
use Lemonade\Admin\Presentation\AdminThumbnail;
use Lemonade\Admin\System\Users\Editor\UsersAdminEditorDefinitionFactory;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class UsersAdminEditorDefinitionFactoryTest extends TestCase
{
    public function testCreateDefinitionKeepsTransportStandardFieldsAndCustomBlocks(): void
    {
        $editor = $this->factory()->create(
            user: $this->user(),
            roleOptions: ['editor' => 'Editor'],
            permissionGroups: $this->permissionGroups(),
            roleEditable: true,
            activeEditable: true,
            activeInput: null,
            hasActiveInput: false,
            permissionEditable: false,
            hasProtectedAuthority: false,
            mode: 'create',
        );
        $profileFields = $this->profileFields($editor);

        self::assertSame('/admin/system/users/create', $editor->form()->action());
        self::assertSame('/admin/system/users/ajax', $editor->form()->actionUrl());
        self::assertSame('create', $editor->form()->actionKey());
        self::assertTrue($editor->form()->navigateToEditAfterCreate());
        self::assertTrue($editor->form()->novalidate());
        self::assertNotNull($editor->saveBar());
        self::assertSame('admin.common.save_changes', $editor->saveBar()->primaryAction()->labelKey());
        self::assertSame('admin.common.discard', $editor->saveBar()->secondaryAction()?->labelKey());
        self::assertSame(['basic'], array_map(static fn($tab): string => $tab->id(), $editor->tabs()));
        self::assertNull($editor->sidebar());
        self::assertSame('email', $profileFields['email']->type());
        self::assertSame('tel', $profileFields['phone']->type());
        $roleField = $this->card($editor, 'access')->blocks()[0];
        self::assertInstanceOf(\Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition::class, $roleField);
        self::assertSame('select', $roleField->type());
        self::assertSame('users::editor.active', $this->customBlock($editor, 'profile', 1)->view());
        self::assertSame('users::editor.password', $this->customBlock($editor, 'security', 0)->view());
        self::assertArrayNotHasKey('version', $profileFields);
    }

    public function testEditDefinitionKeepsVersionAndPermissionCustomContext(): void
    {
        $editor = $this->factory()->create(
            user: $this->user(),
            roleOptions: ['editor' => 'Editor'],
            permissionGroups: $this->permissionGroups(),
            roleEditable: true,
            activeEditable: true,
            activeInput: '1',
            hasActiveInput: true,
            permissionEditable: true,
            hasProtectedAuthority: true,
            mode: 'edit',
            avatarUpload: new AdminFileUploadCollection(
                moduleCode: 'system.users',
                entityId: 7,
                usage: 'thumbnail',
                files: [],
                startUrl: '/admin/files/system.users/thumbnail/7/chunks',
                appendUrlTemplate: '/admin/files/chunks/__upload_id__',
                completeUrlTemplate: '/admin/files/chunks/__upload_id__/complete',
                abortUrlTemplate: '/admin/files/chunks/__upload_id__/abort',
                removeUrlTemplate: '/admin/files/system.users/thumbnail/7/__upload_id__/remove',
                reorderUrl: '/admin/files/system.users/thumbnail/7/reorder',
                renameModalUrlTemplate: '/admin/files/system.users/thumbnail/7/__upload_id__/rename',
                labelKey: 'users.fields.avatar',
                helpKey: 'users.editor.avatar_help',
                editable: true,
                kind: 'image',
                multiple: false,
                sortable: false,
                profile: new AdminFileUploadProfilePresentation(
                    accept: '.jpg',
                    allowedExtensions: ['JPG'],
                    maxBytes: 5242880,
                    maxSizeLabel: '5 MB',
                ),
                presentation: AdminFileUploadPresentation::Avatar,
                fallback: 'AL',
                alt: '',
            ),
        );
        $profileFields = $this->profileFields($editor);
        $permissionBlock = $editor->tabs()[1]->blocks()[0];

        self::assertSame('/admin/system/users/edit/7', $editor->form()->action());
        self::assertSame('/admin/system/users/ajax/7', $editor->form()->actionUrl());
        self::assertSame('save', $editor->form()->actionKey());
        self::assertFalse($editor->form()->navigateToEditAfterCreate());
        self::assertSame(['basic', 'permissions'], array_map(static fn($tab): string => $tab->id(), $editor->tabs()));
        self::assertSame([], $editor->header()?->metadata());
        self::assertNotNull($editor->saveBar());
        self::assertSame('hidden', $profileFields['version']->type());
        self::assertSame([
            'data-lemonade-editor-version' => true,
            'data-lemonade-editor-version-value' => '3',
        ], $profileFields['version']->inputAttributes());
        self::assertInstanceOf(CustomViewBlock::class, $permissionBlock);
        self::assertSame('users::editor.permissions', $permissionBlock->view());
        $sidebar = $editor->sidebar();
        self::assertNotNull($sidebar);
        self::assertCount(1, $sidebar->panels());
        self::assertInstanceOf(SectionBlock::class, $sidebar->panels()[0]);
        self::assertSame('summary', $sidebar->panels()[0]->id());
        self::assertInstanceOf(CustomViewBlock::class, $sidebar->panels()[0]->blocks()[0]);
        self::assertSame('users::editor.summary', $sidebar->panels()[0]->blocks()[0]->view());
        self::assertInstanceOf(AdminThumbnail::class, $sidebar->panels()[0]->blocks()[0]->context()['thumbnail']);
        self::assertInstanceOf(AdminFileUploadCollection::class, $sidebar->panels()[0]->blocks()[0]->context()['avatarUpload']);
        self::assertTrue($sidebar->panels()[0]->blocks()[0]->context()['avatarUpload']->singleImage());
    }

    public function testRestrictedEditOmitsRoleFieldAndKeepsReadonlyActiveStateInCustomContext(): void
    {
        $editor = $this->factory()->create(
            user: $this->user(),
            roleOptions: ['editor' => 'Editor'],
            permissionGroups: $this->permissionGroups(),
            roleEditable: false,
            activeEditable: false,
            activeInput: null,
            hasActiveInput: false,
            permissionEditable: false,
            hasProtectedAuthority: false,
            mode: 'edit',
        );
        $accessCard = $this->card($editor, 'access');

        self::assertCount(1, $accessCard->blocks());
        self::assertSame('users::editor.access-info', $this->customBlock($editor, 'access', 0)->view());
        self::assertSame([
            'activeEditable' => false,
            'activeValue' => '1',
        ], $this->customBlock($editor, 'profile', 2)->context());
    }

    public function testExternalIdentityKeepsIdpManagedProfileFieldsReadonlyWithoutAutocomplete(): void
    {
        $editor = $this->factory()->create(
            user: $this->user(),
            roleOptions: ['editor' => 'Editor'],
            permissionGroups: $this->permissionGroups(),
            roleEditable: true,
            activeEditable: true,
            activeInput: null,
            hasActiveInput: false,
            permissionEditable: true,
            hasProtectedAuthority: false,
            mode: 'edit',
            hasExternalIdentity: true,
        );
        $fields = $this->profileFields($editor);

        foreach (['first_name', 'last_name', 'email'] as $name) {
            self::assertTrue($fields[$name]->isReadonly());
            self::assertSame('off', $fields[$name]->autocompleteValue());
            self::assertFalse($fields[$name]->isDisabled());
        }
        self::assertFalse($fields['phone']->isReadonly());
        self::assertNull($fields['phone']->autocompleteValue());
    }

    public function testLocalProfileFieldsRemainEditableWithoutAutocompleteOverride(): void
    {
        $editor = $this->factory()->create(
            user: $this->user(),
            roleOptions: ['editor' => 'Editor'],
            permissionGroups: $this->permissionGroups(),
            roleEditable: true,
            activeEditable: true,
            activeInput: null,
            hasActiveInput: false,
            permissionEditable: true,
            hasProtectedAuthority: false,
            mode: 'edit',
        );
        $fields = $this->profileFields($editor);

        foreach (['first_name', 'last_name'] as $name) {
            self::assertFalse($fields[$name]->isReadonly());
            self::assertNull($fields[$name]->autocompleteValue());
        }
        self::assertFalse($fields['email']->isReadonly());
        self::assertSame('username', $fields['email']->autocompleteValue());
    }

    /** @return array<string, \Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition> */
    private function profileFields(AdminEditorDefinition $editor): array
    {
        $card = $this->card($editor, 'profile');
        $fields = [];
        foreach ($card->blocks() as $block) {
            if ($block instanceof \Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition) {
                $fields[$block->name()] = $block;
                continue;
            }
            if (!$block instanceof FieldGroupBlock) {
                continue;
            }
            foreach ($block->columns() as $column) {
                $fields[$column->field()->name()] = $column->field();
            }
        }

        return $fields;
    }

    private function card(AdminEditorDefinition $editor, string $id): SectionBlock
    {
        foreach ($editor->tabs()[0]->blocks() as $block) {
            if ($block instanceof SectionBlock && $block->id() === $id) {
                return $block;
            }
        }

        self::fail(sprintf('Missing %s card.', $id));
    }

    private function customBlock(AdminEditorDefinition $editor, string $cardId, int $index): CustomViewBlock
    {
        $block = $this->card($editor, $cardId)->blocks()[$index];
        self::assertInstanceOf(CustomViewBlock::class, $block);

        return $block;
    }

    /** @return array<string, mixed> */
    private function user(): array
    {
        return [
            'id' => 7,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => null,
            'active' => 1,
            'version' => 3,
            'created_at' => '2026-01-02 03:04:05',
            'last_login_at' => '2026-02-03 04:05:06',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function permissionGroups(): array
    {
        return [[
            'moduleCode' => 'system.users',
            'permissions' => [['code' => 'system.users.view', 'state' => 'inherited_allow']],
        ]];
    }

    private function factory(): UsersAdminEditorDefinitionFactory
    {
        $router = new Router();
        $router->getNamed('admin.module.index', '/admin/{module}', ControllerAction::for('TestController', 'index'));
        $router->getNamed('admin.system.module.index', '/admin/system/{module}', ControllerAction::for('TestController', 'index'));
        $router->getNamed('admin.system.module.create', '/admin/system/{module}/create', ControllerAction::for('TestController', 'create'));
        $router->getNamed('admin.system.module.edit', '/admin/system/{module}/edit/{id}', ControllerAction::for('TestController', 'edit'));
        $router->postNamed('admin.system.module.ajax.create', '/admin/system/{module}/ajax', ControllerAction::for('TestController', 'create'));
        $router->postNamed('admin.system.module.ajax.entity', '/admin/system/{module}/ajax/{id}', ControllerAction::for('TestController', 'entity'));
        $router->getNamed('admin.module.create', '/admin/{module}/create', ControllerAction::for('TestController', 'create'));
        $router->getNamed('admin.module.edit', '/admin/{module}/edit/{id}', ControllerAction::for('TestController', 'edit'));
        $router->postNamed('admin.module.ajax.create', '/admin/{module}/ajax', ControllerAction::for('TestController', 'create'));
        $router->postNamed('admin.module.ajax.entity', '/admin/{module}/ajax/{id}', ControllerAction::for('TestController', 'entity'));
        $router->postNamed('admin.users.avatar.upload', '/admin/users/edit/{id}/avatar', ControllerAction::for('TestController', 'upload'));
        $router->postNamed('admin.users.avatar.delete', '/admin/users/edit/{id}/avatar/delete', ControllerAction::for('TestController', 'delete'));

        return new UsersAdminEditorDefinitionFactory(urls: new UrlGenerator(router: $router));
    }
}
