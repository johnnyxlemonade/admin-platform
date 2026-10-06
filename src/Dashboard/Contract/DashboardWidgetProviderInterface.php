<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Contract;

use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;

/**
 * Umoznuje modulu definovat widgety a nacitat jejich obsah
 */
interface DashboardWidgetProviderInterface
{
    /**
     * Vrati definice widgetu poskytovanych modulem
     *
     * @return iterable<DashboardWidgetDefinition>
     */
    public function definitions(): iterable;

    /**
     * Nacte obsah konkretniho dostupneho widgetu
     */
    public function load(
        DashboardWidgetContext $context,
        DashboardWidgetDefinition $definition,
    ): DashboardWidgetContent;
}
