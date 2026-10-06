<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Definuje zdroj aktualni prihlasene identity pro autorizaci
 */
interface CurrentPrincipalProviderInterface
{
    /**
     * Vrati aktualni prihlasenou identitu pro autorizaci
     */
    public function currentPrincipal(): ?AdminPrincipalInterface;

    /**
     * Vrati aktualniho prihlaseneho uzivatele
     */
    public function currentUser(): ?AuthenticatedUser;
}
