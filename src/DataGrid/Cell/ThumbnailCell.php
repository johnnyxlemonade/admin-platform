<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use InvalidArgumentException;
use Lemonade\Admin\Presentation\AdminThumbnail;

/**
 * Zobrazuje shared thumbnail vedle primarniho a doplnkoveho textu v bunce
 */
final readonly class ThumbnailCell implements DataGridCell
{
    /**
     * Vytvori bunku se shared thumbnail presentation a volitelnym odkazem
     */
    public function __construct(
        private string $value,
        private AdminThumbnail $thumbnail,
        private string $secondary,
        private ?string $url = null,
        private bool $download = false,
    ) {
        if ($url === '' || ($download && $url === null)) {
            throw new InvalidArgumentException('DataGrid thumbnail URL must not be empty.');
        }
    }

    /**
     * Vrati hlavni text bunky
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vrati jednotnou presentation obrazku nebo fallbacku
     */
    public function thumbnail(): AdminThumbnail
    {
        return $this->thumbnail;
    }

    /**
     * Vrati doplnkovy text bunky
     */
    public function secondary(): string
    {
        return $this->secondary;
    }

    /**
     * Vrati volitelnou adresu primarniho textu
     */
    public function url(): ?string
    {
        return $this->url;
    }

    /**
     * Urci, zda odkaz preda prohlizeci souborovou download odpoved
     */
    public function download(): bool
    {
        return $this->download;
    }
}
