<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Definuje single nebo multiple select a jeho staticke options
 */
final readonly class SelectField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi select konfiguraci
     *
     * @param array<int|string, string> $options
     * @param array<int|string, string> $optionLabelKeys
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private array $options,
        private array $optionLabelKeys,
        private bool $multiple,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori select z factory facade
     */
    public static function create(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), new AdminEditorInputState(), [], [], false);
    }

    /**
     * Vrati select options
     *
     * @return array<int|string, string>
     */
    public function selectOptions(): array
    {
        return $this->options;
    }

    /**
     * Vrati prekladove klice select options
     *
     * @return array<int|string, string>
     */
    public function selectOptionLabelKeys(): array
    {
        return $this->optionLabelKeys;
    }

    /**
     * Rozhodne zda select prijima vice hodnot
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Nastavi select options
     *
     * @param array<int|string, string> $options
     */
    public function options(array $options): self
    {
        return new self($this->common(), $this->input(), $options, $this->optionLabelKeys, $this->multiple);
    }

    /**
     * Nastavi prekladove klice select options
     *
     * @param array<int|string, string> $keys
     */
    public function optionLabelKeys(array $keys): self
    {
        return new self($this->common(), $this->input(), $this->options, $keys, $this->multiple);
    }

    /**
     * Nastavi multiple transport selectu
     */
    public function multiple(bool $multiple = true): self
    {
        return new self($this->common(), $this->input(), $this->options, $this->optionLabelKeys, $multiple);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->input(), $this->options, $this->optionLabelKeys, $this->multiple);
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self($this->common(), $input, $this->options, $this->optionLabelKeys, $this->multiple);
    }
}
