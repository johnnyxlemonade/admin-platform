<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;

/**
 * Deklaruje systemovy Media modul pro discovery katalog
 */
final class ModuleManifest extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('system.media', ModuleKind::System, MediaModuleProvider::class, 'media.module.name');
    }
}
