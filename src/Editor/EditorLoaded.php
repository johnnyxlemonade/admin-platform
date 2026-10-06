<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class EditorLoaded
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, mixed> $data
     */
    public function __construct(
        private EditorDefinition $definition,
        private array $data,
    ) {}

    /**
     * Zpracovava hodnotu definition v konfiguraci editoru
     */
    public function definition(): EditorDefinition
    {
        return $this->definition;
    }

    /**
     * Zpracovava hodnotu data v konfiguraci editoru
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
