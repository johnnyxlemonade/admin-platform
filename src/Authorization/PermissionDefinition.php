<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

/**
 * Popisuje jedno opravneni a jeho zavislosti
 */
final readonly class PermissionDefinition
{
    public function __construct(
        private string $code,
        private string $moduleCode,
        private string $nameKey,
        private PermissionDelegation $delegation = PermissionDelegation::Normally,
        /** @var list<string> $requires */
        private array $requires = [],
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    public function nameKey(): string
    {
        return $this->nameKey;
    }

    public function delegation(): PermissionDelegation
    {
        return $this->delegation;
    }

    /** @return list<string> */
    public function requires(): array
    {
        return array_values(array_unique($this->requires));
    }
}
