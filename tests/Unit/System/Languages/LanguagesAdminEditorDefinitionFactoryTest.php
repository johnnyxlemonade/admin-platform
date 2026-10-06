<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorStatusVariant;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\System\Languages\Editor\LanguagesAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Languages\Models\LanguageRecord;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class LanguagesAdminEditorDefinitionFactoryTest extends TestCase
{
    public function testModalCreateDefinitionKeepsEditableCodeAndCreateTransport(): void
    {
        $editor = $this->factory()->modalCreate();
        $fields = $this->modalFields($editor);

        self::assertSame('/admin/system/languages/create', $editor->form()->action());
        self::assertSame('/admin/system/languages/ajax', $editor->form()->actionUrl());
        self::assertSame('create', $editor->form()->actionKey());
        self::assertTrue($editor->form()->novalidate());
        self::assertSame([], $editor->tabs());
        self::assertSame('select', $fields['preset']->type());
        self::assertSame('Czech — čeština', $fields['preset']->selectOptions()['cs']);
        self::assertSame('text', $fields['code']->type());
        self::assertTrue($fields['code']->isRequired());
        self::assertSame('number', $fields['sort_order']->type());
        self::assertSame('text', $fields['flag_code']->type());
        self::assertSame([
            ['preset' => 12],
            ['name' => 12],
            ['code' => 4, 'flag_code' => 4, 'sort_order' => 4],
        ], $this->modalLayoutRows($editor));
    }

    public function testModalCreateDefinitionDoesNotExposeStatusOrLegacyNativeName(): void
    {
        $editor = $this->factory()->modalCreate();
        $fields = $this->modalFields($editor);

        self::assertArrayNotHasKey('native_name', $fields);
        self::assertArrayNotHasKey('enabled', $fields);
        self::assertArrayHasKey('preset', $fields);
        self::assertSame('text', $fields['code']->type());
        self::assertTrue($fields['code']->isRequired());
    }

    public function testModalDefinitionUsesOneNameAndReadonlyCodeAndStatus(): void
    {
        $editor = $this->factory()->modal($this->language(enabled: false));
        $fields = $this->modalFields($editor);

        self::assertArrayNotHasKey('native_name', $fields);
        self::assertSame([
            ['name' => 12],
            ['code' => 4, 'flag_code' => 4, 'enabled' => 4],
            ['sort_order' => 4],
        ], $this->modalLayoutRows($editor));
        self::assertTrue($fields['code']->isReadonly());
        self::assertSame('status_display', $fields['enabled']->type());
        self::assertSame('languages.list.disabled', $fields['enabled']->configuredDisplayValueKey());
        self::assertSame(AdminEditorStatusVariant::Muted, $fields['enabled']->displayStatusVariant());
    }

    public function testModalDefinitionUsesTheStandardEditActionTransport(): void
    {
        $editor = $this->factory()->modal($this->language());

        self::assertSame('/admin/system/languages/edit/7', $editor->form()->action());
        self::assertSame('/admin/system/languages/ajax/7', $editor->form()->actionUrl());
        self::assertSame('save', $editor->form()->actionKey());
        self::assertSame([], $editor->tabs());
        self::assertCount(3, $editor->blocks());
    }

    /** @return array<string, \Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition> */
    private function modalFields(AdminEditorDefinition $editor): array
    {
        $fields = [];
        foreach ($editor->blocks() as $block) {
            self::assertInstanceOf(FieldGroupBlock::class, $block);
            foreach ($block->columns() as $column) {
                $fields[$column->field()->name()] = $column->field();
            }
        }

        return $fields;
    }

    /** @return list<array<string, int>> */
    private function modalLayoutRows(AdminEditorDefinition $editor): array
    {
        $rows = [];
        foreach ($editor->blocks() as $block) {
            self::assertInstanceOf(FieldGroupBlock::class, $block);
            $row = [];
            foreach ($block->columns() as $column) {
                $span = $column->md();
                self::assertNotNull($span);
                $row[$column->field()->name()] = $span;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function language(bool $enabled = true): LanguageRecord
    {
        return new LanguageRecord(
            id: 7,
            code: 'cs',
            name: 'Čeština',
            flagCode: 'CZ',
            enabled: $enabled,
            default: true,
            sortOrder: 0,
        );
    }

    private function factory(): LanguagesAdminEditorDefinitionFactory
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

        return new LanguagesAdminEditorDefinitionFactory(urls: new UrlGenerator(router: $router));
    }
}
