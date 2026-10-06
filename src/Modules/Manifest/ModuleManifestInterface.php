<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Manifest;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Popisuje manifest modulu
 */
interface ModuleManifestInterface
{
    /**
     * Vrati kod modulu
     */
    public function code(): string;

    /**
     * Vrati druh modulu
     */
    public function kind(): ModuleKind;

    /**
     * Vrati prekladovy klic nazvu modulu
     */
    public function labelKey(): string;

    /**
     * Vrati poskytovatele sluzeb modulu
     *
     * @return class-string<ServiceProviderInterface>
     */
    public function runtimeProvider(): string;
}
