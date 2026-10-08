<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Framework\Cache\CacheManager;
use Lemonade\Framework\Database\Database;

/**
 * Registruje jazyky dostupne v aplikaci
 */
final class LanguageRegistry
{
    private const PUBLIC_LOCALE_SNAPSHOT_CACHE_KEY = 'admin.public-locale-snapshot.v1';

    /**
     * Nastavuje persistence jazyku a sdilenou persistentni cache public snapshotu
     */
    public function __construct(
        private readonly Database $database,
        private readonly CacheManager $cache,
    ) {}

    /**
     * Vrati atomicky cached stav jazyku pro public CMS runtime
     */
    public function publicLocaleSnapshot(): PublicLocaleSnapshot
    {
        /** @var list<array{code:mixed,enabled:mixed,is_default:mixed}> $rows */
        $rows = $this->cache->rememberForever(
            self::PUBLIC_LOCALE_SNAPSHOT_CACHE_KEY,
            fn(): array => $this->database->select(
                'SELECT code, enabled, is_default FROM system_language',
            ),
        );

        return PublicLocaleSnapshot::fromRows($rows);
    }

    /**
     * Odstrani persistentni public snapshot po uspesne mutaci jazyku
     */
    public function forgetPublicLocaleSnapshot(): void
    {
        $this->cache->forget(self::PUBLIC_LOCALE_SNAPSHOT_CACHE_KEY);
    }

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
