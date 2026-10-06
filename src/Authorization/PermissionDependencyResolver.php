<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use RuntimeException;

/**
 * Doplnuje povinne zavislosti opravneni
 */
final class PermissionDependencyResolver
{
    public function __construct(private readonly PermissionCatalogRegistry $catalog) {}

    /** @return list<string> */
    public function requirementsFor(string $permissionCode): array
    {
        $this->validateCatalog();

        return $this->catalog->definition($permissionCode)?->requires() ?? [];
    }

    /**
     * Adds all transitive prerequisites to a selected permission set.
     *
     * @param list<string> $permissionCodes
     * @return list<string>
     */
    public function withPrerequisites(array $permissionCodes): array
    {
        $this->validateCatalog();
        $selected = [];
        foreach ($permissionCodes as $permissionCode) {
            $this->visit($permissionCode, [], $selected);
        }

        return array_keys($selected);
    }

    /**
     * Applies prerequisite closure to effective permission states.
     *
     * @param array<string,bool> $states
     * @param list<string>|null $explicitlyDenied
     * @return array<string,bool>
     */
    public function withPrerequisitesInStates(array $states, ?array $explicitlyDenied = null): array
    {
        $blocked = $explicitlyDenied ?? array_keys(array_filter($states, static fn(bool $allowed): bool => !$allowed));
        $selected = array_fill_keys(array_keys(array_filter($states, static fn(bool $allowed): bool => $allowed)), true);

        foreach (array_keys($selected) as $permissionCode) {
            $requirements = array_diff($this->withPrerequisites([$permissionCode]), [$permissionCode]);
            if (array_intersect($requirements, $blocked) !== []) {
                unset($selected[$permissionCode]);
            }
        }

        $closed = array_fill_keys($this->withPrerequisites(array_keys($selected)), true);

        foreach ($states as $permissionCode => $allowed) {
            if (!$allowed && !isset($closed[$permissionCode])) {
                $closed[$permissionCode] = false;
            }
        }

        return $closed;
    }

    public function validateCatalog(): void
    {
        $visited = [];
        foreach ($this->catalog->all() as $definition) {
            $this->visit($definition->code(), [], $visited);
        }
    }

    /**
     * @param list<string> $path
     * @param array<string,bool> $selected
     */
    private function visit(string $permissionCode, array $path, array &$selected): void
    {
        $definition = $this->catalog->definition($permissionCode);
        if ($definition === null) {
            throw new RuntimeException(sprintf('Permission dependency references unknown permission "%s".', $permissionCode));
        }

        if (isset($selected[$permissionCode])) {
            return;
        }

        if (in_array($permissionCode, $path, true)) {
            $cycle = [...$path, $permissionCode];
            throw new RuntimeException('Permission dependency cycle: ' . implode(' -> ', $cycle));
        }

        $path[] = $permissionCode;
        foreach ($definition->requires() as $requiredPermission) {
            $this->visit($requiredPermission, $path, $selected);
        }
        $selected[$permissionCode] = true;
    }
}
