<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module\Contract;

use Lemonade\Admin\Presentation\AdminFileCapabilities;

/**
 * Deklaruje standardni file capability vlastnene administracnim modulem
 */
interface AdminModuleFileCapabilitiesInterface
{
    /**
     * Vraci deklarovane file capability modulu
     */
    public function fileCapabilities(): AdminFileCapabilities;
}
