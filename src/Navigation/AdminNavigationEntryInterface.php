<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

/**
 * Urcuje spolecny kontrakt polozky navigace administrace
 */
interface AdminNavigationEntryInterface
{
    /**
     * Vraci stabilni klic polozky navigace
     */
    public function key(): string;

    /**
     * Vraci poradi polozky nebo skupiny navigace
     */
    public function order(): int;
}
