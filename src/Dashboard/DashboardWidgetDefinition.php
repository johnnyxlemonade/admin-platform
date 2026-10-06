<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;

/**
 * Nastavuje identitu, zobrazeni, rozlozeni a pristup widgetu
 */
final readonly class DashboardWidgetDefinition
{
    /**
     * Vytvori widget s identitou, zobrazenim a pristupem
     */
    public function __construct(
        private string $code,
        private string $moduleCode,
        private DashboardWidgetPresentation $presentation,
        private DashboardWidgetLayout $layout,
        private DashboardWidgetAccess $access,
    ) {
        if ($this->code === '' || $this->moduleCode === '') {
            throw new InvalidArgumentException('Dashboard widget identity fields must not be empty.');
        }

        if (!str_starts_with($this->code, $this->moduleCode . '.')) {
            throw new InvalidArgumentException('Dashboard widget code must start with its module code.');
        }

    }

    /**
     * Vrati unikatni kod widgetu
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Vrati kod modulu vlastniciho widget
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vrati zobrazeni widgetu
     */
    public function presentation(): DashboardWidgetPresentation
    {
        return $this->presentation;
    }

    /**
     * Vrati vychozi rozlozeni widgetu
     */
    public function layout(): DashboardWidgetLayout
    {
        return $this->layout;
    }

    /**
     * Vrati pristupovy model widgetu
     */
    public function access(): DashboardWidgetAccess
    {
        return $this->access;
    }
}
