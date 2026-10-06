<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Migration;

/**
 * Nese vysledek migraci modulu
 */
final readonly class ModuleMigrationResult
{
    /** @param list<string> $applied */
    public function __construct(private string $moduleCode, private array $applied) {}

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /** @return list<string> */
    public function applied(): array
    {
        return $this->applied;
    }
}
