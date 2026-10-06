<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use InvalidArgumentException;
use Lemonade\Admin\Select\StaticSelectOptionSource;

/**
 * Popisuje nemennou konfiguraci editorfield
 */
final readonly class EditorFieldDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private string $name,
        private string $type,
        private bool $readOnly,
        private ?string $permission,
        private ?StaticSelectOptionSource $optionSource = null,
    ) {
        $this->assertNonEmpty($name, 'Field name');
        $this->assertNonEmpty($type, 'Field type');
        if ($permission !== null) {
            $this->assertNonEmpty($permission, 'Field permission');
        }

        if ($type === 'select' && $optionSource === null) {
            throw new InvalidArgumentException('Select fields must define an option source.');
        }

        if ($type !== 'select' && $optionSource !== null) {
            throw new InvalidArgumentException('Only select fields may define an option source.');
        }
    }

    /**
     * Zpracovava hodnotu name v konfiguraci editoru
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Zpracovava hodnotu type v konfiguraci editoru
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Zpracovava hodnotu readonly v konfiguraci editoru
     */
    public function readOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Zpracovava hodnotu permission v konfiguraci editoru
     */
    public function permission(): ?string
    {
        return $this->permission;
    }

    /**
     * Zpracovava hodnotu optionsource v konfiguraci editoru
     */
    public function optionSource(): ?StaticSelectOptionSource
    {
        return $this->optionSource;
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
}
