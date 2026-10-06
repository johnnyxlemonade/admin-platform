<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Rozlisuje pripraveny a prazdny obsah dashboardoveho widgetu
 */
enum DashboardWidgetContentState: string
{
    case Ready = 'ready';
    case Empty = 'empty';
}
