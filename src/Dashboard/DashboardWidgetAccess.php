<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;

/**
 * Nastavuje podminky pristupu k dashboardovemu widgetu
 */
final readonly class DashboardWidgetAccess
{
    /**
     * Vytvori pristupovy model s volitelnym opravnenim
     */
    private function __construct(
        private string|null $permission,
    ) {
        if ($this->permission === '') {
            throw new InvalidArgumentException('Permission-based dashboard widgets require a permission.');
        }
    }

    /**
     * Vytvori pristup vyzadujici konkretni opravneni
     */
    public static function permission(string $permission): self
    {
        return new self($permission);
    }

    /**
     * Vytvori pristup pro kazdeho prihlaseneho uzivatele
     */
    public static function authenticated(): self
    {
        return new self(null);
    }

    /**
     * Vrati vyzadovane opravneni pokud je nastaveno
     */
    public function permissionCode(): string|null
    {
        return $this->permission;
    }
}
