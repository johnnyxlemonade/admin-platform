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
}
