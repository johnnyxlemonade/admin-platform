<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Popisuje nemennou konfiguraci admineditoraction
 */
final readonly class AdminEditorActionDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, scalar|bool|null> $attributes
     */
    public function __construct(
        private string $label,
        private ?string $labelKey = null,
        private ?string $href = null,
        private bool $submit = false,
        private bool $primary = false,
        private array $attributes = [],
    ) {
        if (trim($label) === '' && ($labelKey === null || trim($labelKey) === '')) {
            throw new InvalidArgumentException('Action label or label key must not be empty.');
        }
    }

    /**
     * Zpracovava hodnotu label v konfiguraci editoru
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Zpracovava hodnotu labelkey v konfiguraci editoru
     */
    public function labelKey(): ?string
    {
        return $this->labelKey;
    }

    /**
     * Zpracovava hodnotu href v konfiguraci editoru
     */
    public function href(): ?string
    {
        return $this->href;
    }

    /**
     * Zpracovava hodnotu submit v konfiguraci editoru
     */
    public function submit(): bool
    {
        return $this->submit;
    }

    /**
     * Zpracovava hodnotu primary v konfiguraci editoru
     */
    public function primary(): bool
    {
        return $this->primary;
    }

    /**
     * Vytvari HTML atributy z povoleneho nastaveni
     * @return array<string, scalar|bool|null>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
