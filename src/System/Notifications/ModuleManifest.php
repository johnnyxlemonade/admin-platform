<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy management modul oznameni pro module discovery
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    /**
     * Nastavuje identitu, provider a lokalizovany nazev management modulu
     */
    public function __construct()
    {
        parent::__construct('system.notifications', ModuleKind::System, NotificationsModuleProvider::class, 'notifications.module.name');
    }
}
