<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Registry;

use InvalidArgumentException;
use Lemonade\Admin\Modules\Definition\ModuleDefinitionInterface;
use RuntimeException;

/**
 * Registruje moduly aplikace
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleDefinitionInterface> */
    private array $definitions = [];

    public function register(ModuleDefinitionInterface $definition): void
    {
        if (isset($this->definitions[$definition->code()])) {
            throw new InvalidArgumentException(sprintf('Module "%s" is already registered.', $definition->code()));
        }

        $this->definitions[$definition->code()] = $definition;
    }

    public function has(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    public function definition(string $code): ModuleDefinitionInterface
    {
        if (!isset($this->definitions[$code])) {
            throw new RuntimeException(sprintf('Module "%s" is not installed.', $code));
        }
        return $this->definitions[$code];
    }

    /** @return list<ModuleDefinitionInterface> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
