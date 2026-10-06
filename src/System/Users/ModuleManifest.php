<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy manifest Users capability pro shared module discovery
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu systemoveho modulu a jeho provider
     */
    public function __construct()
    {
        parent::__construct('system.users', ModuleKind::System, UsersModuleProvider::class, 'users.module.name');
    }
}
