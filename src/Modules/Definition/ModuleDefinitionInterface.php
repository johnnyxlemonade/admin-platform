<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Definition;

/**
 * Definuje zakladni rozhrani modulu
 */
interface ModuleDefinitionInterface
{
    /**
     * Vrati kod modulu
     */
    public function code(): string;
}
