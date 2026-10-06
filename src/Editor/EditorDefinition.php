<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use InvalidArgumentException;

/**
 * Popisuje nemennou konfiguraci editor
 */
final readonly class EditorDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<EditorFieldDefinition> $fields
     */
    public function __construct(
        private string $loadPermission,
        private string $savePermission,
        private string $createPermission,
        private array $fields,
    ) {
        $this->assertNonEmpty($loadPermission, 'Editor load permission');
        $this->assertNonEmpty($savePermission, 'Editor save permission');
        $this->assertNonEmpty($createPermission, 'Editor create permission');
        $this->assertList($fields, 'Editor fields');

        $fieldNames = [];
        foreach ($fields as $field) {
            if (!$field instanceof EditorFieldDefinition) {
                throw new InvalidArgumentException('Editor fields must contain EditorFieldDefinition instances.');
            }
            if (isset($fieldNames[$field->name()])) {
                throw new InvalidArgumentException('Editor field names must be unique.');
            }

            $fieldNames[$field->name()] = true;
        }
    }

    /**
     * Zpracovava hodnotu loadpermission v konfiguraci editoru
     */
    public function loadPermission(): string
    {
        return $this->loadPermission;
    }

    /**
     * Zpracovava hodnotu savepermission v konfiguraci editoru
     */
    public function savePermission(): string
    {
        return $this->savePermission;
    }

    /**
     * Zpracovava hodnotu createpermission v konfiguraci editoru
     */
    public function createPermission(): string
    {
        return $this->createPermission;
    }

    /**
     * Zpracovava hodnotu fields v konfiguraci editoru
     * @return list<EditorFieldDefinition>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Zpracovava hodnotu field v konfiguraci editoru
     */
    public function field(string $name): ?EditorFieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Odmita prazdnou povinnou hodnotu definice
     */
    private function assertNonEmpty(string $value, string $label): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException($label . ' must not be empty.');
        }
    }

    /**
     * Odmita hodnotu, ktera nema sekvencni seznamove indexy
     * @param list<EditorFieldDefinition> $items
     */
    private function assertList(array $items, string $label): void
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException($label . ' must be a list.');
        }
    }
}
