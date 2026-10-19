<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Definuje checkbox nebo vizualni toggle se stejnym transportem
 */
final readonly class BooleanField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi typ boolean prezentace
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private bool $toggle,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori boolean field z factory facade
     */
    public static function create(string $name, bool $toggle, string $label = '', ?string $labelKey = null): self
    {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), new AdminEditorInputState(), $toggle);
    }

    /**
     * Rozhodne zda se checkbox vykresli jako switch
     */
    public function isToggle(): bool
    {
        return $this->toggle;
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->input(), $this->toggle);
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self($this->common(), $input, $this->toggle);
    }
}
