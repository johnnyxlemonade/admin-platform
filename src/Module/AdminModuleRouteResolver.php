<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Vyhledava administracni modul podle URL segmentu
 */
final readonly class AdminModuleRouteResolver
{
    /**
     * Nastavuje zavislosti potrebne pro praci s administracnimi moduly
     */
    public function __construct(private AdminModuleRegistry $adminModules) {}

    /**
     * Vraci modul dostupny pro pozadovany URL segment
     */
    public function resolve(string $segment): AdminModuleDefinitionInterface
    {
        return $this->adminModules->definition(
            $this->adminModules->moduleCodeByRouteSegment($segment),
        );
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
     * Vraci jmeno canonical management route podle navigation group modulu
     */
    public function managementRouteName(string $segment, string $action): string
    {
        return $this->managementRouteNameForMetadata($this->metadata($segment), $action);
    }

    /**
     * Vraci jmeno canonical management route podle stable kodu modulu
     */
    public function managementRouteNameForModuleCode(string $moduleCode, string $action): string
    {
        return $this->managementRouteNameForMetadata($this->adminModules->definition($moduleCode)->adminMetadata(), $action);
    }

    /**
     * Vraci jmeno canonical management route podle metadat modulu
     */
    public function managementRouteNameForMetadata(AdminModuleMetadata $metadata, string $action): string
    {
        return 'admin.' . $metadata->navigationGroup->value . '.module.' . $action;
    }

    /**
     * Vraci globally unique Admin URL segment modulu podle jeho stableho kodu
     */
    public function segmentForModuleCode(string $moduleCode): string
    {
        return $this->adminModules->definition($moduleCode)->adminMetadata()->routeSegment();
    }
}
