<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use InvalidArgumentException;

/**
 * Definuje textovy input odemykatelny explicitni uzivatelskou akci
 */
final readonly class ReadonlyEditableField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi specifickou konfiguraci odemykatelneho inputu
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private ?string $placeholder,
        private ?string $placeholderKey,
        private ?string $autocomplete,
        private ?string $unlockLabel,
        private ?string $unlockLabelKey,
        private ?HiddenField $marker,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori readonly editable field z factory facade
     */
    public static function create(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self(
            AdminEditorFieldCommon::create($name, $label, $labelKey),
            new AdminEditorInputState(readonly: true),
            null,
            null,
            null,
            null,
            null,
            null,
        );
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
     * Vrati zalozni text unlock akce
     */
    public function unlockText(): ?string
    {
        return $this->unlockLabel;
    }

    /**
     * Vrati prekladovy klic unlock akce
     */
    public function unlockKey(): ?string
    {
        return $this->unlockLabelKey;
    }

    /**
     * Vrati skryty marker zmeny
     */
    public function markerField(): ?HiddenField
    {
        return $this->marker;
    }

    /**
     * Nastavi placeholder a jeho volitelny prekladovy klic
     */
    public function placeholder(string $placeholder, ?string $key = null): self
    {
        return new self($this->common(), $this->input(), $placeholder, $key, $this->autocomplete, $this->unlockLabel, $this->unlockLabelKey, $this->marker);
    }

    /**
     * Nastavi explicitni autocomplete hodnotu
     */
    public function autocomplete(?string $autocomplete): self
    {
        if ($autocomplete !== null && trim($autocomplete) === '') {
            throw new InvalidArgumentException('Autocomplete value must not be empty.');
        }

        return new self($this->common(), $this->input(), $this->placeholder, $this->placeholderKey, $autocomplete, $this->unlockLabel, $this->unlockLabelKey, $this->marker);
    }

    /**
     * Nastavi text unlock akce a jeho prekladovy klic
     */
    public function unlock(string $label, ?string $labelKey = null): self
    {
        return new self($this->common(), $this->input(), $this->placeholder, $this->placeholderKey, $this->autocomplete, $label, $labelKey, $this->marker);
    }

    /**
     * Nastavi skryty marker aktivace editace
     */
    public function marker(?HiddenField $marker): self
    {
        return new self($this->common(), $this->input(), $this->placeholder, $this->placeholderKey, $this->autocomplete, $this->unlockLabel, $this->unlockLabelKey, $marker);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->input(), $this->placeholder, $this->placeholderKey, $this->autocomplete, $this->unlockLabel, $this->unlockLabelKey, $this->marker);
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self($this->common(), $input, $this->placeholder, $this->placeholderKey, $this->autocomplete, $this->unlockLabel, $this->unlockLabelKey, $this->marker);
    }
}
