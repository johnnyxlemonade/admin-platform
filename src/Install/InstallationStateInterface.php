<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install;

/**
 * Zpristupnuje canonical stav instalace pro HTTP lifecycle
 */
interface InstallationStateInterface
{
    /**
     * Zjistuje stav databaze, schematu a root uctu
     *
     * @return array{databaseConnected:bool,databaseSchema:bool,rootAccount:bool}
     */
    public function state(): array;
}
