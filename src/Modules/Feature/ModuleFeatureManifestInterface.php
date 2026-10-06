<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;

/**
 * Popisuje manifest volitelne funkce modulu
 */
interface ModuleFeatureManifestInterface extends ModuleManifestInterface
{
    /**
     * Vrati definice volitelnych funkci modulu
     *
     * @return list<ModuleFeatureDefinition>
     */
    public function featureDefinitions(): array;
}
