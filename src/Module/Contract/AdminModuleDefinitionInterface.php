<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module\Contract;

use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Modules\Definition\ModuleDefinitionInterface;

/**
 * Urcuje kontrakt pro administracni modul
 */
interface AdminModuleDefinitionInterface extends ModuleDefinitionInterface
{
    /**
     * Vraci navigacni a routovaci metadata modulu
     */
    public function adminMetadata(): AdminModuleMetadata;
}
