<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Manifest;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Drzi zakladni udaje manifestu modulu
 */
class ModuleManifestDefinition implements ModuleManifestInterface
{
    /** @param class-string<ServiceProviderInterface> $runtimeProvider */
    public function __construct(
        private readonly string $code,
        private readonly ModuleKind $kind,
        private readonly string $runtimeProvider,
        private readonly string $labelKey = '',
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function kind(): ModuleKind
    {
        return $this->kind;
    }

    public function labelKey(): string
    {
        return $this->labelKey !== '' ? $this->labelKey : $this->code;
    }

    public function runtimeProvider(): string
    {
        return $this->runtimeProvider;
    }
}
