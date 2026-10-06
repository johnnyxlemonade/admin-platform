<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Migration;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;

/**
 * Popisuje migrace modulu
 */
interface ModuleMigrationManifestInterface extends ModuleManifestInterface
{
    /**
     * Vrati migrace modulu
     *
     * @return list<class-string<MigrationInterface>>
     */
    public function migrations(): array;
}
