<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Routing;

use RuntimeException;

/**
 * Overuje verejny prefix routy modulu
 */
final class ModulePublicRoutePrefixManifestValidator
{
    public static function validate(ModulePublicRoutePrefixManifestInterface $manifest): void
    {
        $locales = [];
        foreach ($manifest->publicRoutePrefixDefinitions() as $definition) {
            if (!$definition instanceof ModulePublicRoutePrefixDefinition) {
                throw new RuntimeException(sprintf(
                    'Public route prefix declaration for module "%s" must be a %s.',
                    $manifest->code(),
                    ModulePublicRoutePrefixDefinition::class,
                ));
            }
            if (isset($locales[$definition->locale()])) {
                throw new RuntimeException(sprintf(
                    'Public route prefix locale "%s" is duplicated in module "%s".',
                    $definition->locale(),
                    $manifest->code(),
                ));
            }
            $locales[$definition->locale()] = true;
        }
    }
}
