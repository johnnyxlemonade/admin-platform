<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorBlock;

/**
 * Sdili pouze identitu a prezentacni metadata vsech editorovych poli
 */
abstract readonly class AdminEditorFieldDefinition implements AdminEditorBlock
{
    /**
     * Nastavi spolecnou konfiguraci pole
     */
    protected function __construct(private AdminEditorFieldCommon $common) {}

    /**
     * Vrati transportni nazev pole
     */
    final public function name(): string
    {
        return $this->common->name();
    }

    /**
     * Vrati zalozni text labelu
     */
    final public function labelText(): string
    {
        return $this->common->label();
    }

    /**
     * Vrati prekladovy klic labelu
     */
    final public function labelTranslationKey(): ?string
    {
        return $this->common->labelKey();
    }

    /**
     * Vrati zalozni text napovedy
     */
    final public function helpText(): ?string
    {
        return $this->common->help();
    }

    /**
     * Vrati prekladovy klic napovedy
     */
    final public function helpTranslationKey(): ?string
    {
        return $this->common->helpKey();
    }

    /**
     * Rozhodne zda pole deklaruje strukturalni default
     */
    final public function hasDefaultValue(): bool
    {
        return $this->common->hasDefaultValue();
    }

    /**
     * Vrati strukturalni default pole
     */
    final public function structuralDefaultValue(): mixed
    {
        return $this->common->defaultValue();
    }

    /**
     * Nastavi zalozni text labelu
     */
    final public function label(string $label): static
    {
        return $this->withCommon($this->common->withLabel($label));
    }

    /**
     * Nastavi prekladovy klic labelu
     */
    final public function labelKey(?string $labelKey): static
    {
        return $this->withCommon($this->common->withLabelKey($labelKey));
    }

    /**
     * Nastavi napovedu a jeji volitelny prekladovy klic
     */
    final public function help(string $help, ?string $key = null): static
    {
        return $this->withCommon($this->common->withHelp($help, $key));
    }

    /**
     * Nastavi prekladovy klic napovedy
     */
    final public function helpKey(?string $helpKey): static
    {
        return $this->withCommon($this->common->withHelpKey($helpKey));
    }

    /**
     * Nastavi strukturalni default vcetne explicitniho null
     */
    final public function defaultValue(mixed $value): static
    {
        return $this->withCommon($this->common->withDefaultValue($value));
    }

    /**
     * Vrati spolecnou konfiguraci pro konkretni immutable rekonstrukci
     */
    final protected function common(): AdminEditorFieldCommon
    {
        return $this->common;
    }

    /**
     * Vytvori stejny konkretni field s novymi spolecnymi daty
     */
    abstract protected function withCommon(AdminEditorFieldCommon $common): static;
}
