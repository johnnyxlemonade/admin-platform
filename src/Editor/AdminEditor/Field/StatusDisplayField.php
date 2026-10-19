<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Definuje stavovy needitovatelny vystup
 */
final readonly class StatusDisplayField extends AdminEditorFieldDefinition
{
    /**
     * Nastavi variantu a prezentacni metadata statusu
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        private AdminEditorStatusVariant $variant,
        private ?string $displayValueKey,
        private bool $indicator,
    ) {
        parent::__construct($common);
    }

    /**
     * Vytvori status vystup z factory facade
     */
    public static function create(
        string $name,
        string $label = '',
        ?string $labelKey = null,
        AdminEditorStatusVariant $variant = AdminEditorStatusVariant::Muted,
    ): self {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), $variant, null, true);
    }

    /**
     * Vrati vizualni variantu statusu
     */
    public function variant(): AdminEditorStatusVariant
    {
        return $this->variant;
    }

    /**
     * Vrati prekladovy klic zobrazene hodnoty
     */
    public function displayTranslationKey(): ?string
    {
        return $this->displayValueKey;
    }

    /**
     * Rozhodne zda status zobrazuje indikator
     */
    public function displaysIndicator(): bool
    {
        return $this->indicator;
    }

    /**
     * Nastavi prekladovy klic zobrazene hodnoty
     */
    public function displayValueKey(?string $key): self
    {
        return new self($this->common(), $this->variant, $key, $this->indicator);
    }

    /**
     * Nastavi viditelnost stavoveho indikatoru
     */
    public function indicator(bool $indicator = true): self
    {
        return new self($this->common(), $this->variant, $this->displayValueKey, $indicator);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->variant, $this->displayValueKey, $this->indicator);
    }
}
