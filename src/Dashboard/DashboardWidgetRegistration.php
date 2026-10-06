<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;

/**
 * Spojuje definici widgetu s jeho poskytovatelem obsahu
 */
final readonly class DashboardWidgetRegistration
{
    /**
     * Vytvori registraci widgetu u poskytovatele obsahu
     */
    public function __construct(
        private DashboardWidgetDefinition $definition,
        private DashboardWidgetProviderInterface $provider,
    ) {}

    /**
     * Vrati definici registrovaneho widgetu
     */
    public function definition(): DashboardWidgetDefinition
    {
        return $this->definition;
    }

    /**
     * Vrati poskytovatele obsahu registrovaneho widgetu
     */
    public function provider(): DashboardWidgetProviderInterface
    {
        return $this->provider;
    }
}
