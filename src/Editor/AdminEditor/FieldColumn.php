<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class FieldColumn
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private AdminEditorFieldDefinition $field,
        private ?int $sm = null,
        private ?int $md = null,
        private ?int $lg = null,
        private ?int $xl = null,
        private ?int $xxl = null,
    ) {
        foreach ([
            'sm' => $sm,
            'md' => $md,
            'lg' => $lg,
            'xl' => $xl,
            'xxl' => $xxl,
        ] as $breakpoint => $span) {
            if ($span !== null && ($span < 1 || $span > 12)) {
                throw new InvalidArgumentException(sprintf('Field column %s span must be between 1 and 12.', $breakpoint));
            }
        }
    }

    /**
     * Zpracovava hodnotu field v konfiguraci editoru
     */
    public function field(): AdminEditorFieldDefinition
    {
        return $this->field;
    }

    /**
     * Zpracovava hodnotu md v konfiguraci editoru
     */
    public function md(): ?int
    {
        return $this->md;
    }

    /**
     * Zpracovava hodnotu breakpoints v konfiguraci editoru
     * @return array<string, int>
     */
    public function breakpoints(): array
    {
        return array_filter([
            'sm' => $this->sm,
            'md' => $this->md,
            'lg' => $this->lg,
            'xl' => $this->xl,
            'xxl' => $this->xxl,
        ], static fn(?int $span): bool => $span !== null);
    }
}
