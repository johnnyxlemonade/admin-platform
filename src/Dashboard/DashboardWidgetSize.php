<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Nabizi velikosti pouzitelne pro rozlozeni dashboardoveho widgetu
 */
enum DashboardWidgetSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Full = 'full';
}
