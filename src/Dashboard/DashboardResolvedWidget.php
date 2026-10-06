<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Popisuje widget pripraveny pro vykresleni na dashboardu
 */
final readonly class DashboardResolvedWidget
{
    /**
     * Vytvori widget s prirazenim, poradi a velikosti
     */
    public function __construct(
        private DashboardWidgetRegistration $registration,
        private int $position,
        private DashboardWidgetSize $size,
    ) {}

    /**
     * Vrati registraci widgetu
     */
    public function registration(): DashboardWidgetRegistration
    {
        return $this->registration;
    }

    /**
     * Vrati pozici widgetu v rozlozeni
     */
    public function position(): int
    {
        return $this->position;
    }

    /**
     * Vrati velikost widgetu v rozlozeni
     */
    public function size(): DashboardWidgetSize
    {
        return $this->size;
    }
}
