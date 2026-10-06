<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Routing;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;

/**
 * Popisuje verejny prefix rout modulu
 */
interface ModulePublicRoutePrefixManifestInterface extends ModuleManifestInterface
{
    /**
     * Vrati definice verejnych prefixu rout modulu
     *
     * @return list<ModulePublicRoutePrefixDefinition>
     */
    public function publicRoutePrefixDefinitions(): array;
}
