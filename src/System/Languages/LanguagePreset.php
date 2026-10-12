<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

use InvalidArgumentException;

/**
 * Popisuje jeden canonical preset systemoveho jazyka
 */
final readonly class LanguagePreset
{
    /**
     * Nastavuje staticka metadata jednoho jazyka
     */
    public function __construct(
        private string $code,
        private string $displayName,
        private string $nativeName,
    ) {
        if (preg_match('/\\A[a-z]{2}\\z/D', $code) !== 1) {
            throw new InvalidArgumentException('Language preset code must be an ISO 639-1 code.');
        }

        if (trim($displayName) === '' || trim($nativeName) === '') {
            throw new InvalidArgumentException('Language preset names must not be empty.');
        }

    }

    /**
     * Vraci ISO 639-1 kod jazyka
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Vraci anglicky zobrazovany nazev jazyka
     */
    public function displayName(): string
    {
        return $this->displayName;
    }

    /**
     * Vraci nazev jazyka v jeho vlastnim zapisu
     */
    public function nativeName(): string
    {
        return $this->nativeName;
    }
}
