<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Routing\AdminRoutingConfiguration;

/**
 * Overuje bezpecny cil po prihlaseni do administrace
 */
final class InternalAdminDestination
{
    /**
     * Overi zda cil vede na bezpecnou interni cestu administrace
     */
    public static function isSafe(?string $destination, AdminRoutingConfiguration $routing): bool
    {
        $path = is_string($destination) ? explode('?', $destination, 2)[0] : '';

        return is_string($destination)
            && $routing->contains($path)
            && !str_starts_with($destination, '//')
            && !str_contains($destination, '\\')
            && preg_match('/[\r\n]/', $destination) !== 1;
    }
}
