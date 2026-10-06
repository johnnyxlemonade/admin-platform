<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

use RuntimeException;

/**
 * Overuje manifest volitelne funkce
 */
final class ModuleFeatureManifestValidator
{
    public static function validate(ModuleFeatureManifestInterface $manifest): void
    {
        $codes = [];
        foreach ($manifest->featureDefinitions() as $feature) {
            if (!$feature instanceof ModuleFeatureDefinition) {
                throw new RuntimeException(sprintf('Feature declaration for module "%s" must be a %s.', $manifest->code(), ModuleFeatureDefinition::class));
            }
            if (isset($codes[$feature->code()])) {
                throw new RuntimeException(sprintf('Feature code "%s" is duplicated in module "%s".', $feature->code(), $manifest->code()));
            }
            $codes[$feature->code()] = true;
        }
    }
}
