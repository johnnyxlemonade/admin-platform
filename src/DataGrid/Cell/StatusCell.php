<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use InvalidArgumentException;

/**
 * Zobrazuje stav v bunce tabulky
 */
final readonly class StatusCell implements DataGridCell
{
    /**
     * Vytvori stavovou bunku s volitelnym prekladovym klicem
     */
    public function __construct(
        private string $value,
        private StatusVariant $variant,
        private ?string $translationKey = null,
    ) {
        if ($translationKey === '') {
            throw new InvalidArgumentException('DataGrid status translation key must not be empty.');
        }
    }

    /**
     * Vrati text stavu
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vrati vzhled stavu
     */
    public function variant(): StatusVariant
    {
        return $this->variant;
    }

    /**
     * Vrati volitelny prekladovy klic stavu
     */
    public function translationKey(): ?string
    {
        return $this->translationKey;
    }
}
