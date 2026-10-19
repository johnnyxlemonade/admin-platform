<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Sdili pouze flags a atributy skutecnych formularovych vstupu
 */
abstract readonly class AdminEditorInputFieldDefinition extends AdminEditorFieldDefinition
{
    /**
     * Nastavi spolecnou a vstupni konfiguraci pole
     */
    protected function __construct(AdminEditorFieldCommon $common, private AdminEditorInputState $input)
    {
        parent::__construct($common);
    }

    /**
     * Rozhodne zda je vstup povinny
     */
    final public function isRequired(): bool
    {
        return $this->input->required();
    }

    /**
     * Rozhodne zda je vstup pouze pro cteni
     */
    final public function isReadonly(): bool
    {
        return $this->input->readonly();
    }

    /**
     * Rozhodne zda je vstup zakazany
     */
    final public function isDisabled(): bool
    {
        return $this->input->disabled();
    }

    /**
     * Vrati vlastni HTML atributy vstupu
     *
     * @return array<string, scalar|bool|null>
     */
    final public function inputAttributes(): array
    {
        return $this->input->attributes();
    }

    /**
     * Nastavi required flag vstupu
     */
    final public function required(bool $required = true): static
    {
        return $this->withInputState($this->input->withRequired($required));
    }

    /**
     * Nastavi readonly flag vstupu
     */
    final public function readonly(bool $readonly = true): static
    {
        return $this->withInputState($this->input->withReadonly($readonly));
    }

    /**
     * Nastavi disabled flag vstupu
     */
    final public function disabled(bool $disabled = true): static
    {
        return $this->withInputState($this->input->withDisabled($disabled));
    }

    /**
     * Nahrazuje vlastni HTML atributy vstupu
     *
     * @param array<string, scalar|bool|null> $attributes
     */
    final public function attributes(array $attributes): static
    {
        return $this->withInputState($this->input->withAttributes($attributes));
    }

    /**
     * Vrati vstupni stav pro konkretni immutable rekonstrukci
     */
    final protected function input(): AdminEditorInputState
    {
        return $this->input;
    }

    /**
     * Vytvori stejny konkretni input field s novym vstupnim stavem
     */
    abstract protected function withInputState(AdminEditorInputState $input): static;
}
