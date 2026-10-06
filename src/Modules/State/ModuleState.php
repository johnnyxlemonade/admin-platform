<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\State;

use Lemonade\Admin\Modules\Definition\ModuleKind;

/**
 * Nese stav modulu
 */
final readonly class ModuleState
{
    public function __construct(
        private string $code,
        private bool $available,
        private bool $installed,
        private bool $enabled,
        private ?ModuleKind $kind,
        private bool $missingCode,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function installed(): bool
    {
        return $this->installed;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function kind(): ?ModuleKind
    {
        return $this->kind;
    }

    public function missingCode(): bool
    {
        return $this->missingCode;
    }

    public function system(): bool
    {
        return $this->kind === ModuleKind::System;
    }
}
