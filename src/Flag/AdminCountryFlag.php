<?php

declare(strict_types=1);

namespace Lemonade\Admin\Flag;

use InvalidArgumentException;

/**
 * Poskytuje Unicode vlajku pro normalizovany kod zeme
 */
final readonly class AdminCountryFlag
{
    /**
     * Ulozi normalizovany kod zeme
     */
    private function __construct(
        private string $countryCode,
    ) {}

    /**
     * Vrati vlajku pouze pro platny dvoupismenny kod zeme
     */
    public static function tryFrom(string $countryCode): ?self
    {
        if (preg_match('/\A[A-Z]{2}\z/D', $countryCode) !== 1) {
            return null;
        }

        return new self($countryCode);
    }

    /**
     * Vrati vlajku nebo ohlasi neplatny kod zeme
     */
    public static function from(string $countryCode): self
    {
        return self::tryFrom($countryCode) ?? throw new InvalidArgumentException(sprintf(
            'Admin country code must be a normalized two-letter ASCII code, got "%s".',
            $countryCode,
        ));
    }

    /**
     * Vrati normalizovany kod zeme
     */
    public function countryCode(): string
    {
        return $this->countryCode;
    }

    /**
     * Vrati Unicode symbol vlajky zeme
     */
    public function unicode(): string
    {
        return self::regionalIndicator($this->countryCode[0])
            . self::regionalIndicator($this->countryCode[1]);
    }

    /**
     * Prevede pismeno kodu zeme na regionalni Unicode indikator
     */
    private static function regionalIndicator(string $letter): string
    {
        $codePoint = 0x1F1E6 + ord($letter) - ord('A');

        return pack(
            'C*',
            0xF0 | ($codePoint >> 18),
            0x80 | (($codePoint >> 12) & 0x3F),
            0x80 | (($codePoint >> 6) & 0x3F),
            0x80 | ($codePoint & 0x3F),
        );
    }
}
