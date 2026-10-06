<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Dashboard;

use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Navigation\AdminNavigationGroup;
use Lemonade\Admin\Navigation\AdminNavigationItem;

/**
 * Poskytuje dashboardovy widget z jiz autorizovane Admin navigace
 */
final class ModulesDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    /**
     * Nastavuje navigaci filtrovou autorizaci pred vytvorenim widgetu
     */
    public function __construct(private readonly AdminNavigation $navigation) {}

    /**
     * Deklaruje widget odkazu na dostupne administracni moduly
     *
     * @return iterable<DashboardWidgetDefinition>
     */
    public function definitions(): iterable
    {
        yield new DashboardWidgetDefinition(
            code: 'system.modules.active',
            moduleCode: 'system.modules',
            presentation: new DashboardWidgetPresentation(
                translationGroup: 'modules',
                titleKey: 'modules.widgets.active.title',
                descriptionKey: 'modules.widgets.active.description',
                icon: AdminIcon::Boxes,
                contentView: 'modules::widgets.active',
                emptyMessageKey: 'modules.widgets.active.empty',
            ),
            layout: new DashboardWidgetLayout(
                defaultOrder: 300,
                defaultSize: DashboardWidgetSize::Medium,
                supportedSizes: [DashboardWidgetSize::Small, DashboardWidgetSize::Medium, DashboardWidgetSize::Large, DashboardWidgetSize::Full],
            ),
            access: DashboardWidgetAccess::authenticated(),
        );
    }

    /**
     * Promita pouze jiz autorizovane modulove polozky navigace do obsahu widgetu
     */
    public function load(
        DashboardWidgetContext $context,
        DashboardWidgetDefinition $definition,
    ): DashboardWidgetContent {
        unset($context, $definition);

        $modules = [];
        foreach ($this->navigation->items() as $entry) {
            $items = $entry instanceof AdminNavigationGroup ? $entry->items() : ($entry instanceof AdminNavigationItem ? [$entry] : []);
            foreach ($items as $item) {
                if ($item->moduleCode() === null) {
                    continue;
                }

                $modules[] = [
                    'label' => $item->label(),
                    'icon' => $item->icon()?->value,
                    'route' => $item->route(),
                    'routeParameters' => $item->routeParameters(),
                ];
            }
        }

        if ($modules === []) {
            return DashboardWidgetContent::empty();
        }

        return DashboardWidgetContent::ready(['modules' => $modules]);
    }
}
