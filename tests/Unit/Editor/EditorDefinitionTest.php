<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor;

use InvalidArgumentException;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use PHPUnit\Framework\TestCase;

final class EditorDefinitionTest extends TestCase
{
    public function testItExposesTypedFieldsForTheEditorProtocol(): void
    {
        $field = new EditorFieldDefinition(
            name: 'title',
            type: 'text',
            readOnly: false,
            permission: null,
        );

        $definition = $this->definition(fields: [$field]);

        self::assertSame([$field], $definition->fields());
        self::assertSame('title', $field->name());
        self::assertSame('text', $field->type());
        self::assertFalse($field->readOnly());
        self::assertNull($field->permission());
    }

    public function testItRejectsEmptyFieldIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EditorFieldDefinition(
            name: '',
            type: 'text',
            readOnly: false,
            permission: null,
        );
    }

    public function testItRejectsEmptyEditorPermissions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EditorDefinition(
            loadPermission: '',
            savePermission: 'cms.articles.edit',
            createPermission: 'cms.articles.create',
            fields: [],
        );
    }

    public function testItRejectsDuplicateFieldNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $field = new EditorFieldDefinition(
            name: 'title',
            type: 'text',
            readOnly: false,
            permission: null,
        );

        $this->definition(fields: [$field, $field]);
    }

    public function testItExposesAnExplicitOptionSourceForSelectFields(): void
    {
        $source = new StaticSelectOptionSource([new SelectOptionDefinition('editor', 'Editor')]);
        $field = new EditorFieldDefinition(
            name: 'role',
            type: 'select',
            readOnly: false,
            permission: null,
            optionSource: $source,
        );

        self::assertSame($source, $field->optionSource());
    }

    /**
     * @param list<EditorFieldDefinition> $fields
     */
    private function definition(array $fields): EditorDefinition
    {
        return new EditorDefinition(
            loadPermission: 'cms.articles.view',
            savePermission: 'cms.articles.edit',
            createPermission: 'cms.articles.create',
            fields: $fields,
        );
    }
}
