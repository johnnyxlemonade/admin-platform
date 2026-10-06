<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

/**
 * Sjednocuje individualni zmeny opravneni
 */
final class PermissionOverrideNormalizer
{
    /**
     * @param list<string> $rolePermissionCodes
     * @param array<string,bool> $requestedStates
     * @return array<string,string>
     */
    public function normalize(array $rolePermissionCodes, array $requestedStates): array
    {
        $inherited = array_fill_keys($rolePermissionCodes, true);
        $overrides = [];
        foreach ($requestedStates as $code => $state) {
            $requestedAllowed = $state;
            if ($requestedAllowed !== isset($inherited[$code])) {
                $overrides[$code] = $requestedAllowed ? 'allow' : 'deny';
            }
        }
        return $overrides;
    }
}
