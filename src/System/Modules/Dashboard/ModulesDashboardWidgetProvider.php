<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Dashboard;

use Lemonade\Admin\Authorization\PermissionSection;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Navigation\AdminNavigationGroup;
use Lemonade\Admin\Navigation\AdminNavigationItem;

/**
 * Poskytuje dashboardovy widget z jiz autorizovane Admin navigace
 */
final readonly class ModulesDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    /**
     * Nastavuje autorizovanou navigaci a registr se sdilenym clenenim modulu
     */
    public function __construct(
        private AdminNavigation $navigation,
        private AdminModuleRegistry $registry,
    ) {}

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

        $sections = [];
        foreach ($this->navigation->items() as $entry) {
            $items = $entry instanceof AdminNavigationGroup ? $entry->items() : ($entry instanceof AdminNavigationItem ? [$entry] : []);
            foreach ($items as $item) {
                $moduleCode = $item->moduleCode();
                if ($moduleCode === null) {
                    continue;
                }

                $sections[$this->registry->permissionSection($moduleCode)->value][] = [
                    'label' => $item->label(),
                    'icon' => $item->icon()?->value,
                    'route' => $item->route(),
                    'routeParameters' => $item->routeParameters(),
                ];
            }
        }

        if ($sections === []) {
            return DashboardWidgetContent::empty();
        }

        $groups = [];
        foreach (PermissionSection::cases() as $section) {
            if (isset($sections[$section->value])) {
                $groups[] = ['section' => $section->value, 'modules' => $sections[$section->value]];
            }
        }

        return DashboardWidgetContent::ready(['groups' => $groups]);
    }
}
