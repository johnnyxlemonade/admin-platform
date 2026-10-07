<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use RuntimeException;

/**
 * Vyhledava administracni modul podle URL segmentu
 */
final class AdminModuleRouteResolver
{
    /**
     * Nastavuje zavislosti potrebne pro praci s administracnimi moduly
     */
    public function __construct(
        private readonly AdminModuleRegistry $adminModules,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * Vraci modul dostupny pro pozadovany URL segment
     */
    public function resolve(string $segment): AdminModuleDefinitionInterface
    {
        $moduleCode = $this->adminModules->moduleCodeByRouteSegment($segment);

        if (!$this->modules->has($moduleCode)) {
            throw new RuntimeException(sprintf('Admin module "%s" is not registered in Core.', $moduleCode));
        }

        return $this->adminModules->definition($moduleCode);
    }

    /**
     * Vraci metadata modulu pro pozadovany URL segment
     */
    public function metadata(string $segment): AdminModuleMetadata
    {
        $moduleCode = $this->adminModules->moduleCodeByRouteSegment($segment);

        return $this->adminModules->definition($moduleCode)->adminMetadata();
    }

    /**
     * Vraci jmeno canonical management route podle ownershipu modulu
     */
    public function managementRouteName(string $segment, string $action): string
    {
        return $this->managementRouteNameForModuleCode($this->resolve($segment)->code(), $action);
    }

    /**
     * Vraci jmeno canonical management route podle stable kodu modulu
     */
    public function managementRouteNameForModuleCode(string $moduleCode, string $action): string
    {
        return match (true) {
            str_starts_with($moduleCode, 'system.') => 'admin.system.module.' . $action,
            $this->isCmsModuleCode($moduleCode) => 'admin.cms.module.' . $action,
            default => 'admin.module.' . $action,
        };
    }

    /**
     * Rozhoduje, zda module code patri CMS management ownershipu
     */
    public function isCmsModuleCode(string $moduleCode): bool
    {
        return str_starts_with($moduleCode, 'cms.');
    }

    /**
     * Rozhoduje, zda segment patri CMS management ownershipu
     */
    public function isCmsSegment(string $segment): bool
    {
        return $this->isCmsModuleCode($this->resolve($segment)->code());
    }

    /**
     * Vraci globally unique Admin URL segment modulu podle jeho stableho kodu
     */
    public function segmentForModuleCode(string $moduleCode): string
    {
        return $this->adminModules->definition($moduleCode)->adminMetadata()->routeSegment();
    }
}
