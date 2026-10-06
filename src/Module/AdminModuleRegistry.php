<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use InvalidArgumentException;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use RuntimeException;

/**
 * Uchovava registrovane administracni moduly a jejich URL segmenty
 */
final class AdminModuleRegistry
{
    /** @var array<string, AdminModuleDefinitionInterface> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $routeSegments = [];

    /**
     * Nastavuje zavislosti potrebne pro praci s administracnimi moduly
     */
    public function __construct(private readonly AdminNavigationGroupRegistry $navigationGroups) {}

    /**
     * Registruje modul a overuje jedinecnost jeho kodu a segmentu
     */
    public function register(AdminModuleDefinitionInterface $definition): void
    {
        $moduleCode = $definition->code();
        if (isset($this->definitions[$moduleCode])) {
            throw new InvalidArgumentException(sprintf('Admin module "%s" is already registered.', $moduleCode));
        }

        $segment = $definition->adminMetadata()->routeSegment();
        if (preg_match('/^[a-z][a-z0-9-]*$/', $segment) !== 1) {
            throw new InvalidArgumentException(sprintf('Module route segment "%s" is invalid.', $segment));
        }
        if (isset($this->routeSegments[$segment])) {
            throw new InvalidArgumentException(sprintf('Module route segment "%s" is already registered.', $segment));
        }

        $navigationGroup = $definition->adminMetadata()->navigationGroup();
        if ($navigationGroup !== null && !$this->navigationGroups->has($navigationGroup)) {
            throw new InvalidArgumentException(sprintf('Admin navigation group "%s" is not registered.', $navigationGroup));
        }

        $this->definitions[$moduleCode] = $definition;
        $this->routeSegments[$segment] = $moduleCode;
    }

    /**
     * Rozhoduje, zda je modul zaregistrovan
     */
    public function has(string $moduleCode): bool
    {
        return isset($this->definitions[$moduleCode]);
    }

    /**
     * Vraci definici zaregistrovaneho modulu
     */
    public function definition(string $moduleCode): AdminModuleDefinitionInterface
    {
        if (!$this->has($moduleCode)) {
            throw new RuntimeException(sprintf('Admin module "%s" is not registered.', $moduleCode));
        }

        return $this->definitions[$moduleCode];
    }

    /**
     * Vraci kod modulu pro zaregistrovany URL segment
     */
    public function moduleCodeByRouteSegment(string $segment): string
    {
        if (!isset($this->routeSegments[$segment])) {
            throw new RuntimeException(sprintf('Module route segment "%s" is not registered.', $segment));
        }

        return $this->routeSegments[$segment];
    }

    /**
     * Vraci vsechny zaregistrovane definice modulu
     * @return list<AdminModuleDefinitionInterface>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
