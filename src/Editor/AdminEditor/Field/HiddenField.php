<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Definuje skrytou transportni hodnotu bez prezentacnich capabilities
 */
final readonly class HiddenField extends AdminEditorFieldDefinition
{
    /**
     * Nastavi konfiguraci skryteho vstupu
     *
     * @param array<string, scalar|bool|null> $attributes
     */
    private function __construct(AdminEditorFieldCommon $common, private array $attributes)
    {
        parent::__construct($common);
    }

    /**
     * Vytvori skryty field z factory facade
     */
    public static function create(string $name): self
    {
        return new self(AdminEditorFieldCommon::create($name), []);
    }

    /**
     * Vrati vlastni HTML atributy
     *
     * @return array<string, scalar|bool|null>
     */
    public function inputAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Nahrazuje vlastni HTML atributy
     *
     * @param array<string, scalar|bool|null> $attributes
     */
    public function attributes(array $attributes): self
    {
        return new self($this->common(), $attributes);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->attributes);
    }
}
