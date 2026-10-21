<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Manifest;

use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Drzi zakladni udaje manifestu modulu
 */
class ModuleManifestDefinition implements ModuleManifestInterface
{
    /** @param class-string<ServiceProviderInterface> $runtimeProvider */
    public function __construct(
        private readonly string $code,
        private readonly string $runtimeProvider,
        private readonly string $labelKey = '',
    ) {}

    /**
     * Vrati stabilni kod modulu
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Vrati prekladovy klic nazvu modulu
     */
    public function labelKey(): string
    {
        return $this->labelKey !== '' ? $this->labelKey : $this->code;
    }

    /**
     * Vrati provider registrujici runtime capabilities modulu
     */
    public function runtimeProvider(): string
    {
        return $this->runtimeProvider;
    }
}
