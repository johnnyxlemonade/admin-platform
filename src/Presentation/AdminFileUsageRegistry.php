<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Eviduje modulove deklarace file usage pred finalizaci Admin bootstrapu
 */
final class AdminFileUsageRegistry
{
    /** @var array<string,AdminFileUsageDefinition> */
    private array $definitions = [];

    /**
     * Prida jedinecnou deklaraci usage
     */
    public function register(AdminFileUsageDefinition $definition): void
    {
        $key = $definition->moduleCode() . ':' . $definition->usage();
        if (isset($this->definitions[$key])) {
            throw new \LogicException(sprintf('File usage "%s" is already registered.', $key));
        }

        $this->definitions[$key] = $definition;
    }

    /**
     * Vrati deklaraci usage nebo odmitne neznamy transportni target
     */
    public function require(string $moduleCode, string $usage): AdminFileUsageDefinition
    {
        $definition = $this->definitions[$moduleCode . ':' . $usage] ?? null;
        if ($definition === null) {
            throw new \OutOfBoundsException('File usage is not registered.');
        }

        return $definition;
    }
}
