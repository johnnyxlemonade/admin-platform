<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

/**
 * Nese vysledek synchronizace opravneni
 */
final readonly class PermissionSyncReport
{
    /** @param list<string> $stale */
    public function __construct(
        private int $inserted,
        private int $updated,
        private int $unchanged,
        private array $stale,
    ) {}

    public function inserted(): int
    {
        return $this->inserted;
    }

    public function updated(): int
    {
        return $this->updated;
    }

    public function unchanged(): int
    {
        return $this->unchanged;
    }

    /** @return list<string> */
    public function stale(): array
    {
        return $this->stale;
    }
}
