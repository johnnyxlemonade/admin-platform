<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use InvalidArgumentException;

/**
 * Popisuje operaci zaznamenanou v auditu
 */
final readonly class AuditOperation
{
    public function __construct(
        private string $moduleCode,
        private string $code,
        private AuditActor $actor,
    ) {
        if (trim($moduleCode) === '' || trim($code) === '') {
            throw new InvalidArgumentException('Audit operation module and code must not be empty.');
        }
    }

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function actor(): AuditActor
    {
        return $this->actor;
    }
}
