<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Database\Database;

/**
 * Registruje jazyky dostupne v aplikaci
 */
final class LanguageRegistry
{
    public function __construct(private readonly Database $database) {}

    public function defaultLocale(): string
    {
        $rows = $this->database->select('SELECT code FROM system_language WHERE enabled=1 AND is_default=1');
        return (string) ($rows[0]['code'] ?? 'cs');
    }

    public function isEnabledNonDefault(string $locale): bool
    {
        return $this->database->select('SELECT code FROM system_language WHERE code=? AND enabled=1 AND is_default=0', [$locale]) !== [];
    }

    public function isKnownLocale(string $locale): bool
    {
        return $this->database->select('SELECT code FROM system_language WHERE code=?', [$locale]) !== [];
    }

    /** @return list<array{code:string,name:string,is_default:int}> */
    public function enabledLocales(): array
    {
        /** @var list<array{code:string,name:string,is_default:int}> $locales */
        $locales = $this->database->select(
            'SELECT code,name,is_default FROM system_language WHERE enabled=1 ORDER BY sort_order,code',
        );

        return $locales;
    }
}
