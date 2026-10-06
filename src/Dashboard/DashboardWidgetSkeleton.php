<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Urcuje loading skeleton pro obsah dashboardoveho widgetu
 */
enum DashboardWidgetSkeleton: string
{
    case List = 'list';
    case Stat = 'stat';
}
