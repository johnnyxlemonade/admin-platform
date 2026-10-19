<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Definuje prosty needitovatelny textovy vystup
 */
final readonly class ReadonlyDisplayField extends AdminEditorFieldDefinition
{
    /**
     * Nastavi prezentacni konfiguraci vystupu
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        private ?string $displayValueKey,
        private bool $code,
    ) {
        parent::__construct($common);
    }

    /**
     * Vytvori readonly vystup z factory facade
     */
    public static function create(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), null, false);
    }

    /**
     * Vrati prekladovy klic zobrazene hodnoty
     */
    public function displayTranslationKey(): ?string
    {
        return $this->displayValueKey;
    }

    /**
     * Rozhodne zda se hodnota vykresli jako code
     */
    public function displaysAsCode(): bool
    {
        return $this->code;
    }

    /**
     * Nastavi prekladovy klic zobrazene hodnoty
     */
    public function displayValueKey(?string $key): self
    {
        return new self($this->common(), $key, $this->code);
    }

    /**
     * Nastavi code prezentaci hodnoty
     */
    public function asCode(bool $code = true): self
    {
        return new self($this->common(), $this->displayValueKey, $code);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->displayValueKey, $this->code);
    }
}
