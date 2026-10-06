<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy Languages modul pro module discovery
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu, provider a lokalizovany nazev systemoveho modulu
     */
    public function __construct()
    {
        parent::__construct('system.languages', ModuleKind::System, LanguagesModuleProvider::class, 'languages.module.name');
    }
}
