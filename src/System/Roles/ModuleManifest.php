<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy modul roli pro shared discovery katalog
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu a provider systemoveho modulu roli
     */
    public function __construct()
    {
        parent::__construct('system.roles', ModuleKind::System, RolesModuleProvider::class, 'roles.module.name');
    }
}
