<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Cache\CacheManager;

/**
 * Sdili persistentni cache explicitnich override skupin a jejich revizi
 */
final class TranslationOverrideCache
{
    /** @var list<callable(string,string):void> */
    private array $requestForgetters = [];

    /**
     * Nastavuje frameworkovou cache pro lokalizacni runtime
     */
    public function __construct(private readonly CacheManager $cache) {}

    /**
     * Nacte skupinu overrides a ulozi i prazdny vysledek bez expirace
     *
     * @param callable():array<string,string> $load
     * @return array<string,string>
     */
    public function group(string $locale, string $group, callable $load): array
    {
        /** @var array<string,string> $overrides */
        $overrides = $this->cache->rememberForever(
            $this->groupKey($locale, $group),
            $load,
        );

        return $overrides;
    }

    /**
     * Nacte revizi overrides a ulozi nulu bez expirace
     *
     * @param callable():int $load
     */
    public function revision(string $locale, string $group, callable $load): int
    {
        /** @var int $revision */
        $revision = $this->cache->rememberForever(
            $this->revisionKey($locale, $group),
            $load,
        );

        return $revision;
    }

    /**
     * Odstrani pouze hodnoty ovlivnene jednou zmenenou skupinou
     */
    public function forget(string $locale, string $group): void
    {
        $this->cache->forget($this->groupKey($locale, $group));
        $this->cache->forget($this->revisionKey($locale, $group));

        foreach ($this->requestForgetters as $forget) {
            $forget($locale, $group);
        }
    }

    /**
     * Registruje L1 invalidaci sluzby, ktera cte stejnou override identitu
     *
     * @param callable(string,string):void $forget
     */
    public function registerRequestForgetter(callable $forget): void
    {
        $this->requestForgetters[] = $forget;
    }

    /**
     * Vytvari bezpecny a stabilni key pro override skupinu
     */
    private function groupKey(string $locale, string $group): string
    {
        return 'admin.translation-overrides.group.' . $this->identityHash($locale, $group);
    }

    /**
     * Vytvari bezpecny a stabilni key pro revision skupiny
     */
    private function revisionKey(string $locale, string $group): string
    {
        return 'admin.translation-overrides.revision.' . $this->identityHash($locale, $group);
    }

    /**
     * Oddeluje libovolne locale a group bez nepovoleneho znaku cache key
     */
    private function identityHash(string $locale, string $group): string
    {
        return hash('sha256', $locale . "\0" . $group);
    }
}
