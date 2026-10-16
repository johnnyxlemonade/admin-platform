<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Registruje service ID dashboardovych provideru
 */
final class DashboardWidgetRegistry
{
    /** @var list<string> */
    private array $providerServiceIds = [];

    /**
     * Registruje provider jako root-safe service referenci
     */
    public function registerProvider(string $providerServiceId): void
    {
        if (!in_array($providerServiceId, $this->providerServiceIds, true)) {
            $this->providerServiceIds[] = $providerServiceId;
        }
    }

    /**
     * Vraci service ID vsech registrovanych provideru
     *
     * @return list<string>
     */
    public function providerServiceIds(): array
    {
        return $this->providerServiceIds;
    }
}
