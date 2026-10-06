<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy auditni modul pro module discovery
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu, provider a lokalizovany nazev systemoveho modulu
     */
    public function __construct()
    {
        parent::__construct('system.audit', ModuleKind::System, AuditModuleProvider::class, 'audit.module.name');
    }
}
