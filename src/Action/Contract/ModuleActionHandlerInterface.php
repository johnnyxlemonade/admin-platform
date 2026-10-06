<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Contract;

use Lemonade\Admin\Action\ModuleActionResult;

/**
 * Umoznuje modulu provest akci nad zaznamem
 */
interface ModuleActionHandlerInterface
{
    /**
     * Provede akci a vrati jeji vysledek
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult;
}
