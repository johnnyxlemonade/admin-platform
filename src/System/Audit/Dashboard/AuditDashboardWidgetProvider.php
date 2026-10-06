<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit\Dashboard;

use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetFooterAction;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Dashboard\DashboardWidgetSkeleton;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\System\Audit\AuditEventPresenter;
use Lemonade\Admin\System\Audit\Models\AuditLogModel;

/**
 * Poskytuje dashboardovy widget s poslednimi udalostmi systemoveho auditu
 */
final class AuditDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    /**
     * Nastavi zdroj udalosti a presenter jejich lokalizovane podoby
     */
    public function __construct(
        private readonly AuditLogModel $audit,
        private readonly AuditEventPresenter $presenter,
    ) {}

    /**
     * Deklaruje widget poslednich udalosti dostupny s pravem na audit
     *
     * @return iterable<DashboardWidgetDefinition>
     */
    public function definitions(): iterable
    {
        yield new DashboardWidgetDefinition(
            code: 'system.audit.recent',
            moduleCode: 'system.audit',
            presentation: new DashboardWidgetPresentation(
                translationGroup: 'audit',
                titleKey: 'audit.widgets.recent.title',
                descriptionKey: 'audit.widgets.recent.description',
                icon: AdminIcon::ClockHistory,
                contentView: 'audit::widgets.recent',
                emptyMessageKey: 'audit.widgets.recent.empty',
                skeleton: DashboardWidgetSkeleton::List,
            ),
            layout: new DashboardWidgetLayout(
                defaultOrder: 200,
                defaultSize: DashboardWidgetSize::Medium,
                supportedSizes: [DashboardWidgetSize::Medium, DashboardWidgetSize::Large, DashboardWidgetSize::Full],
            ),
            access: DashboardWidgetAccess::permission('system.audit.view'),
        );
    }

    /**
     * Nacita posledni udalosti a pripravuje jejich polozky pro sablonu widgetu
     */
    public function load(
        DashboardWidgetContext $context,
        DashboardWidgetDefinition $definition,
    ): DashboardWidgetContent {
        $footer = new DashboardWidgetFooterAction('audit.widgets.recent.view_all', 'admin.system.module.index', ['module' => 'audit']);
        $events = $this->audit->recentForDashboard(6);
        if ($events === []) {
            return DashboardWidgetContent::empty($footer);
        }

        $items = [];
        foreach ($events as $event) {
            $items[] = [
                'actor' => $this->presenter->actor($event),
                'action' => $this->presenter->description($event['event_code'], $event['payload']),
                'icon' => $this->presenter->icon($event['event_code'])->value,
                'module' => $this->presenter->moduleLabel($event['module_code']),
                'createdAt' => $event['created_at'],
            ];
        }

        return DashboardWidgetContent::ready(['items' => $items], $footer);
    }
}
