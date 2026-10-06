<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use InvalidArgumentException;

/**
 * Zobrazuje odkaz v bunce tabulky
 */
final readonly class LinkCell implements DataGridCell
{
    /**
     * Vytvori odkazovou bunku s volitelnym modalem
     */
    public function __construct(
        private string $value,
        private string $url,
        private ?string $modalUrl = null,
        private ?string $modalSize = null,
    ) {
        if ($url === '') {
            throw new InvalidArgumentException('DataGrid link URL must not be empty.');
        }
    }

    /**
     * Vrati text odkazu
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vrati cilovou adresu odkazu
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Vrati volitelnou adresu modalu
     */
    public function modalUrl(): ?string
    {
        return $this->modalUrl;
    }

    /**
     * Vrati volitelnou velikost modalu
     */
    public function modalSize(): ?string
    {
        return $this->modalSize;
    }
}
