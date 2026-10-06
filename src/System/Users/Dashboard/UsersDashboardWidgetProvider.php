<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Dashboard;

use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Dashboard\DashboardWidgetSkeleton;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\System\Users\Models\UserModel;

/**
 * Poskytuje dashboard widget s poctem aktivnich uzivatelu
 */
final class UsersDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    /**
     * Nastavi persistence zdroj poctu aktivnich uzivatelu
     */
    public function __construct(private readonly UserModel $users) {}

    /**
     * Deklaruje dostupnost a vzhled widgetu aktivnich uzivatelu
     *
     * @return iterable<DashboardWidgetDefinition>
     */
    public function definitions(): iterable
    {
        yield new DashboardWidgetDefinition(
            code: 'system.users.active',
            moduleCode: 'system.users',
            presentation: new DashboardWidgetPresentation(
                translationGroup: 'users',
                titleKey: 'users.widgets.active.title',
                descriptionKey: 'users.widgets.active.description',
                icon: AdminIcon::People,
                contentView: 'users::widgets.active',
                skeleton: DashboardWidgetSkeleton::Stat,
            ),
            layout: new DashboardWidgetLayout(
                defaultOrder: 100,
                defaultSize: DashboardWidgetSize::Small,
                supportedSizes: [DashboardWidgetSize::Small, DashboardWidgetSize::Medium],
            ),
            access: DashboardWidgetAccess::permission('system.users.view'),
        );
    }

    /**
     * Nacte aktualni pocet aktivnich uzivatelu pro dashboard response
     */
    public function load(
        DashboardWidgetContext $context,
        DashboardWidgetDefinition $definition,
    ): DashboardWidgetContent {
        return DashboardWidgetContent::ready([
            'activeUserCount' => $this->users->activeDashboardCount(),
        ]);
    }
}
