<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use InvalidArgumentException;
use Lemonade\Admin\Flag\AdminCountryFlag;

/**
 * Zobrazuje odkaz doplneny o vlajku zeme
 */
final readonly class FlagLinkCell implements DataGridCell
{
    private string $flag;

    /**
    * Vytvori odkazovou bunku s vlajkou a volitelnym modalem
    */
    public function __construct(
        private string $value,
        private string $url,
        string $flagCode,
        private ?string $modalUrl = null,
        private ?string $modalSize = null,
    ) {
        if ($url === '') {
            throw new InvalidArgumentException('DataGrid link URL must not be empty.');
        }

        $this->flag = AdminCountryFlag::from($flagCode)->unicode();
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
     * Vrati Unicode symbol vlajky
     */
    public function flag(): string
    {
        return $this->flag;
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
