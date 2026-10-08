<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;

/**
 * Odvozuje dostupnost verejne routy z lifecycle modulu
 */
final class ModuleLifecyclePublicModuleStateResolver implements PublicModuleStateResolverInterface
{
    /**
     * Nastavuje canonical resolver lifecycle modulu
     */
    public function __construct(private readonly ModuleStateResolver $modules) {}

    /**
     * Overi, zda modul muze obslouzit verejnou CMS routu
     */
    public function isDiscoveredInstalledAndEnabled(string $moduleCode): bool
    {
        $state = $this->modules->state($moduleCode);

        return $state->available() && $state->installed() && $state->enabled();
    }
}
