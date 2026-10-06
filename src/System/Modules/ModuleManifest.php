<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy management modul pro module discovery
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu, provider a lokalizovany nazev management modulu
     */
    public function __construct()
    {
        parent::__construct('system.modules', ModuleKind::System, ModulesModuleProvider::class, 'modules.module.name');
    }
}
