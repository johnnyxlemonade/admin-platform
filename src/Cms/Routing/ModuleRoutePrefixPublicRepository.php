<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Modules\Persistence\ModuleRoutePrefixModel;
use Lemonade\Cms\Routing\Module\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Framework\Cache\CacheManager;

/**
 * Zpristupnuje synchronizovane verejne prefixy rout modulu
 */
final class ModuleRoutePrefixPublicRepository implements PublicModuleRoutePrefixRepositoryInterface
{
    private const CACHE_KEY = 'admin.public-module-route-prefixes.v1';

    /**
     * Nastavuje persistence modulu s kanonickymi prefixy
     */
    public function __construct(
        private readonly ModuleRoutePrefixModel $prefixes,
        private readonly CacheManager $cache,
    ) {}

    /**
     * Vrati prefix modulu v pozadovane lokalizaci
     */
    public function prefixFor(string $moduleCode, string $locale): ?string
    {
        return $this->all()[$moduleCode][$locale] ?? null;
    }

    /**
     * Vrati modul vlastnici dany lokalizovany verejny prefix
     */
    public function moduleFor(string $locale, string $prefix): ?string
    {
        foreach ($this->all() as $moduleCode => $prefixes) {
            if (($prefixes[$locale] ?? null) === $prefix) {
                return $moduleCode;
            }
        }

        return null;
    }

    /**
     * Zrusi persistentni snapshot po uspesne synchronizaci prefixu
     */
    public function forgetCachedPrefixes(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /** @return array<string, array<string, string>> */
    private function all(): array
    {
        /** @var array<string, array<string, string>> $prefixes */
        $prefixes = $this->cache->rememberForever(self::CACHE_KEY, fn(): array => $this->prefixes->all());

        return $prefixes;
    }
}
