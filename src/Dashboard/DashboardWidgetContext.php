<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Poskytuje identitu a lokalizaci pro vykresleni widgetu
 */
final class DashboardWidgetContext
{
    /**
     * Vytvori kontext pro konkretniho lokalniho uzivatele
     */
    public function __construct(
        private readonly AuthenticatedUser $localUser,
        private readonly string $locale,
    ) {}

    /**
     * Vytvori kontext dashboardu pro lokalniho uzivatele
     */
    public static function forLocalUser(AuthenticatedUser $user, string $locale): self
    {
        return new self($user, $locale);
    }

    /**
     * Vrati lokalniho uzivatele dashboardu
     */
    public function localUser(): AuthenticatedUser
    {
        return $this->localUser;
    }

    /**
     * Vrati kanonicky klic vlastnika osobnich dashboardovych dat
     */
    public function ownerKey(): string
    {
        return 'user:' . $this->localUser->id();
    }

    /**
     * Vrati aktivni lokalizaci dashboardu
     */
    public function locale(): string
    {
        return $this->locale;
    }
}
