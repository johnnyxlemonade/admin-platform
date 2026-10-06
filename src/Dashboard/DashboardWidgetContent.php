<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Poskytuje stav a data obsahu dashboardoveho widgetu
 */
final readonly class DashboardWidgetContent
{
    /**
     * Vytvori obsah s daty, stavem a volitelnou akci v zapati
     *
     * @param array<string, mixed> $viewData
     */
    private function __construct(
        private DashboardWidgetContentState $state,
        private array $viewData,
        private DashboardWidgetFooterAction|null $footerAction,
    ) {}

    /**
     * Vytvori pripraveny obsah widgetu
     *
     * @param array<string, mixed> $viewData
     */
    public static function ready(array $viewData, DashboardWidgetFooterAction|null $footerAction = null): self
    {
        return new self(DashboardWidgetContentState::Ready, $viewData, $footerAction);
    }

    /**
     * Vytvori prazdny obsah widgetu
     */
    public static function empty(DashboardWidgetFooterAction|null $footerAction = null): self
    {
        return new self(DashboardWidgetContentState::Empty, [], $footerAction);
    }

    /**
     * Vrati stav obsahu widgetu
     */
    public function state(): DashboardWidgetContentState
    {
        return $this->state;
    }

    /**
     * Vrati data predavana sablone widgetu
     *
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        return $this->viewData;
    }

    /**
     * Vrati volitelnou akci v zapati widgetu
     */
    public function footerAction(): DashboardWidgetFooterAction|null
    {
        return $this->footerAction;
    }
}
