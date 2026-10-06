<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

/**
 * Drzi katalog dostupnych opravneni
 */
final class PermissionCatalogRegistry
{
    /** @var array<string, PermissionDefinition> */
    private array $definitions = [];

    public function register(PermissionDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->code()] = $definition;
        }
    }

    /** @return list<PermissionDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function definition(string $code): ?PermissionDefinition
    {
        return $this->definitions[$code] ?? null;
    }
}
