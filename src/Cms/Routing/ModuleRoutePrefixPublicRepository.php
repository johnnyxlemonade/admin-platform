<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Modules\Persistence\ModuleRoutePrefixModel;
use Lemonade\Cms\Routing\Module\PublicModuleRoutePrefixRepositoryInterface;

/**
 * Zpristupnuje synchronizovane verejne prefixy rout modulu
 */
final class ModuleRoutePrefixPublicRepository implements PublicModuleRoutePrefixRepositoryInterface
{
    /**
     * Nastavuje persistence modulu s kanonickymi prefixy
     */
    public function __construct(private readonly ModuleRoutePrefixModel $prefixes) {}

    /**
     * Vrati prefix modulu v pozadovane lokalizaci
     */
    public function prefixFor(string $moduleCode, string $locale): ?string
    {
        return $this->prefixes->prefixFor($moduleCode, $locale);
    }

    /**
     * Vrati modul vlastnici dany lokalizovany verejny prefix
     */
    public function moduleFor(string $locale, string $prefix): ?string
    {
        return $this->prefixes->moduleFor($locale, $prefix);
    }
}
