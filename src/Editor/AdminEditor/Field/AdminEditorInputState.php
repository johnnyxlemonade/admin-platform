<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Drzi flags a HTML atributy sdilene skutecnymi formularovymi vstupy
 */
final readonly class AdminEditorInputState
{
    /**
     * Nastavi vstupni flags a atributy
     *
     * @param array<string, scalar|bool|null> $attributes
     */
    public function __construct(
        private bool $required = false,
        private bool $readonly = false,
        private bool $disabled = false,
        private array $attributes = [],
    ) {}

    /**
     * Rozhodne zda je vstup povinny
     */
    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Rozhodne zda je vstup pouze pro cteni
     */
    public function readonly(): bool
    {
        return $this->readonly;
    }

    /**
     * Rozhodne zda je vstup zakazany
     */
    public function disabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Vrati vlastni HTML atributy vstupu
     *
     * @return array<string, scalar|bool|null>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Vytvori stav s novym required flagem
     */
    public function withRequired(bool $required): self
    {
        return new self($required, $this->readonly, $this->disabled, $this->attributes);
    }

    /**
     * Vytvori stav s novym readonly flagem
     */
    public function withReadonly(bool $readonly): self
    {
        return new self($this->required, $readonly, $this->disabled, $this->attributes);
    }

    /**
     * Vytvori stav s novym disabled flagem
     */
    public function withDisabled(bool $disabled): self
    {
        return new self($this->required, $this->readonly, $disabled, $this->attributes);
    }

    /**
     * Vytvori stav s novymi HTML atributy
     *
     * @param array<string, scalar|bool|null> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self($this->required, $this->readonly, $this->disabled, $attributes);
    }
}
