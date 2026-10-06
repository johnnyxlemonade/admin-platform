<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy modul prekladov pro module discovery
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu a provider systemoveho modulu prekladu
     */
    public function __construct()
    {
        parent::__construct('system.translations', ModuleKind::System, TranslationsModuleProvider::class, 'translations.module.name');
    }
}
