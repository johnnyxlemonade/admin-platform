<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;

/**
 * Odvozuje dostupnost verejne CMS routy z Composer katalogu
 */
final readonly class ComposerPublicModuleStateResolver implements PublicModuleStateResolverInterface
{
    /**
     * Nastavuje canonical Composer katalog modulu
     */
    public function __construct(private ModuleCatalog $modules) {}

    /**
     * Overi, zda je modul soucasti Composer instalace aplikace
     */
    public function isDiscoveredInstalledAndEnabled(string $moduleCode): bool
    {
        return $this->modules->has($moduleCode);
    }
}
