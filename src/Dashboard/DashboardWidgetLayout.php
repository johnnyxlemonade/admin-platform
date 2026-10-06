<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;

/**
 * Nastavuje vychozi rozlozeni dashboardoveho widgetu
 */
final readonly class DashboardWidgetLayout
{
    /**
     * Vytvori rozlozeni s vychozi velikosti a podporovanymi velikostmi
     *
     * @param list<DashboardWidgetSize> $supportedSizes
     */
    public function __construct(
        private int $defaultOrder,
        private DashboardWidgetSize $defaultSize,
        private array $supportedSizes,
    ) {
        if (!array_is_list($this->supportedSizes)) {
            throw new InvalidArgumentException('Dashboard widget supported sizes must be a list.');
        }

        if (!in_array($this->defaultSize, $this->supportedSizes, true)) {
            throw new InvalidArgumentException('Dashboard widget default size must be supported.');
        }

        $sizeValues = array_map(static fn(DashboardWidgetSize $size): string => $size->value, $this->supportedSizes);
        if (count(array_unique($sizeValues)) !== count($this->supportedSizes)) {
            throw new InvalidArgumentException('Dashboard widget supported sizes must be unique.');
        }
    }

    /**
     * Vrati vychozi poradi widgetu
     */
    public function defaultOrder(): int
    {
        return $this->defaultOrder;
    }

    /**
     * Vrati vychozi velikost widgetu
     */
    public function defaultSize(): DashboardWidgetSize
    {
        return $this->defaultSize;
    }

    /**
     * Vrati velikosti podporovane widgetem
     *
     * @return list<DashboardWidgetSize>
     */
    public function supportedSizes(): array
    {
        return $this->supportedSizes;
    }
}
