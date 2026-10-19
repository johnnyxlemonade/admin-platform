<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use InvalidArgumentException;

/**
 * Definuje rodinu nativnich textovych HTML inputu
 */
final readonly class TextInputField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi presne typovanou konfiguraci nativniho inputu
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private NativeInputType $inputType,
        private ?string $placeholder,
        private ?string $placeholderKey,
        private ?string $autocomplete,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori nativni input z factory facade
     */
    public static function create(
        string $name,
        NativeInputType $inputType,
        string $label = '',
        ?string $labelKey = null,
    ): self {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), new AdminEditorInputState(), $inputType, null, null, null);
    }

    /**
     * Vrati nativni HTML input type
     */
    public function inputType(): NativeInputType
    {
        return $this->inputType;
    }

    /**
     * Vrati zalozni placeholder
     */
    public function placeholderText(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Vrati prekladovy klic placeholderu
     */
    public function placeholderKey(): ?string
    {
        return $this->placeholderKey;
    }

    /**
     * Vrati autocomplete hodnotu
     */
    public function autocompleteValue(): ?string
    {
        return $this->autocomplete;
    }

    /**
     * Nastavi placeholder a jeho volitelny prekladovy klic
     */
    public function placeholder(string $placeholder, ?string $key = null): self
    {
        return new self($this->common(), $this->input(), $this->inputType, $placeholder, $key, $this->autocomplete);
    }

    /**
     * Nastavi explicitni autocomplete hodnotu
     */
    public function autocomplete(?string $autocomplete): self
    {
        if ($autocomplete !== null && trim($autocomplete) === '') {
            throw new InvalidArgumentException('Autocomplete value must not be empty.');
        }

        return new self($this->common(), $this->input(), $this->inputType, $this->placeholder, $this->placeholderKey, $autocomplete);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->input(), $this->inputType, $this->placeholder, $this->placeholderKey, $this->autocomplete);
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self($this->common(), $input, $this->inputType, $this->placeholder, $this->placeholderKey, $this->autocomplete);
    }
}
