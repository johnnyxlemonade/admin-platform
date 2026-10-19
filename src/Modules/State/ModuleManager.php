<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\State;

/**
 * Ridi instalaci a zivotni cyklus modulu
 */
final class ModuleManager
{
    public function __construct(private readonly ModuleStateResolver $states) {}

    public function available(string $code): bool
    {
        return $this->states->state($code)->available();
    }

    public function installed(string $code): bool
    {
        return $this->states->state($code)->installed();
    }

    public function enabled(string $code): bool
    {
        return $this->states->state($code)->enabled();
    }
}
