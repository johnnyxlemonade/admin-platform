<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;

/**
 * Popisuje Composer balicek a manifest modulu dostupneho aplikaci
 */
final readonly class AvailableModule
{
    /**
     * Nastavuje manifest a zobrazitelna Composer metadata modulu
     */
    public function __construct(
        private ModuleManifestInterface $manifest,
        private string $packageName,
        private string $version,
        private string $description,
    ) {}

    /**
     * Vraci runtime manifest modulu
     */
    public function manifest(): ModuleManifestInterface
    {
        return $this->manifest;
    }

    /**
     * Vraci stabilni kod modulu
     */
    public function code(): string
    {
        return $this->manifest->code();
    }

    /**
     * Vraci Composer nazev vlastniciho balicku
     */
    public function packageName(): string
    {
        return $this->packageName;
    }

    /**
     * Vraci verzi vlastniciho Composer balicku
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Vraci popis vlastniciho Composer balicku
     */
    public function description(): string
    {
        return $this->description;
    }
}
