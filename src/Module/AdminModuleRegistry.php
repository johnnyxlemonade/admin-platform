<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use InvalidArgumentException;
use Lemonade\Admin\Authorization\PermissionSection;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Module\Exception\AdminModuleNotFoundException;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;

/**
 * Uchovava registrovane administracni moduly a jejich URL segmenty
 */
final class AdminModuleRegistry
{
    /** @var array<string, AdminModuleDefinitionInterface> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $routeSegments = [];

    private AdminModuleContributionLoader $contributions;

    /**
     * Nastavuje zavislosti potrebne pro praci s administracnimi moduly
     */
    public function __construct(
        private readonly AdminNavigationGroupRegistry $navigationGroups,
        ?AdminModuleContributionLoader $contributions = null,
    ) {
        $this->contributions = $contributions ?? new AdminModuleContributionLoader();
    }

    /**
     * Copies static definitions and attaches the current scope's deferred contributions
     */
    public function withContributions(AdminModuleContributionLoader $contributions): self
    {
        $copy = clone $this;
        $copy->contributions = $contributions;

        return $copy;
    }

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
        if (!$this->navigationGroups->has($navigationGroup->value)) {
            throw new InvalidArgumentException(sprintf('Admin navigation group "%s" is not registered.', $navigationGroup->value));
        }

        $this->definitions[$moduleCode] = $definition;
        $this->routeSegments[$segment] = $moduleCode;
    }

    /**
     * Loads deferred contributions and checks whether the module exists
     */
    public function has(string $moduleCode): bool
    {
        $this->contributions->load();

        return isset($this->definitions[$moduleCode]);
    }

    /**
     * Loads deferred contributions and returns the module definition
     */
    public function definition(string $moduleCode): AdminModuleDefinitionInterface
    {
        if (!$this->has($moduleCode)) {
            throw new AdminModuleNotFoundException(sprintf('Admin module "%s" is not registered.', $moduleCode));
        }

        return $this->definitions[$moduleCode];
    }

    /**
     * Respektuje explicitni sekci modulu a zachovava dosavadni systemove cleneni
     */
    public function permissionSection(string $moduleCode): PermissionSection
    {
        if ($this->has($moduleCode)) {
            $section = $this->definition($moduleCode)->adminMetadata()->permissionSection();
            if ($section !== null) {
                return $section;
            }
        }

        return str_starts_with($moduleCode, 'system.') ? PermissionSection::System : PermissionSection::Modules;
    }

    /**
     * Loads deferred contributions and resolves a route segment
     */
    public function moduleCodeByRouteSegment(string $segment): string
    {
        $this->contributions->load();

        if (!isset($this->routeSegments[$segment])) {
            throw new AdminModuleNotFoundException(sprintf('Module route segment "%s" is not registered.', $segment));
        }

        return $this->routeSegments[$segment];
    }

    /**
     * Loads deferred contributions and returns every module definition
     * @return list<AdminModuleDefinitionInterface>
     */
    public function all(): array
    {
        $this->contributions->load();

        return array_values($this->definitions);
    }

    /**
     * Returns the currently registered modules, including any already materialized deferred contributions
     *
     * @return list<AdminModuleDefinitionInterface>
     */
    public function registeredWithoutLoading(): array
    {
        return array_values($this->definitions);
    }
}
