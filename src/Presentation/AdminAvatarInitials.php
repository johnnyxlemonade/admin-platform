<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Sklada kratke inicialy pro avatar prihlaseneho uzivatele
 */
final class AdminAvatarInitials
{
    /**
     * Vraci inicialy z dostupnych udaju prihlaseneho uzivatele
     */
    public static function resolve(?AuthenticatedUser $user): string
    {
        return self::fromValues(
            firstName: $user?->firstName(),
            lastName: $user?->lastName(),
            displayName: null,
            email: $user?->email(),
        );
    }

    /**
     * Vybere inicialy ze jmena, zobrazeneho jmena nebo e-mailu
     */
    public static function fromValues(?string $firstName, ?string $lastName, ?string $displayName, ?string $email): string
    {
        $firstName = self::normalize($firstName);
        $lastName = self::normalize($lastName);
        if ($firstName !== '' && $lastName !== '') {
            return self::initial($firstName) . self::initial($lastName);
        }

        if ($firstName !== '') {
            return self::initial($firstName);
        }

        if ($lastName !== '') {
            return self::initial($lastName);
        }

        $displayNameParts = self::words($displayName);
        if (count($displayNameParts) >= 2) {
            return self::initial($displayNameParts[0]) . self::initial($displayNameParts[array_key_last($displayNameParts)]);
        }

        if ($displayNameParts !== []) {
            return self::initial($displayNameParts[0]);
        }

        $email = self::normalize($email);
        if ($email !== '') {
            return self::initial(explode('@', $email, 2)[0]);
        }

        return '?';
    }

    /**
     * Rozdeli normalizovane zobrazene jmeno na slova
     *
     * @return list<string>
     */
    private static function words(?string $value): array
    {
        $value = self::normalize($value);
        if ($value === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    /**
     * Odstrani okrajove mezery z volitelne hodnoty
     */
    private static function normalize(?string $value): string
    {
        return trim((string) $value);
    }

    /**
     * Vraci prvni znak prevedeny na velke pismeno
     */
    private static function initial(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
    }
}
