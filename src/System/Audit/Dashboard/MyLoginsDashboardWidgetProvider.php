<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit\Dashboard;

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
use Lemonade\Admin\System\Audit\PersonalLoginAuditReaderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Poskytuje vlastnikovi dashboardu widget s jeho nedavnymi prihlasenimi
 */
final class MyLoginsDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    /**
     * Nastavi zdroj osobnich prihlaseni a jejich lokalizaci
     */
    public function __construct(
        private readonly PersonalLoginAuditReaderInterface $audit,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * Deklaruje widget prihlaseni dostupny kazdemu prihlasenemu uzivateli
     *
     * @return iterable<DashboardWidgetDefinition>
     */
    public function definitions(): iterable
    {
        yield new DashboardWidgetDefinition(
            code: 'system.audit.my_logins',
            moduleCode: 'system.audit',
            presentation: new DashboardWidgetPresentation(
                translationGroup: 'audit',
                titleKey: 'audit.widgets.my_logins.title',
                descriptionKey: null,
                icon: AdminIcon::ShieldCheck,
                contentView: 'audit::widgets.my-logins',
                emptyMessageKey: 'audit.widgets.my_logins.empty',
                skeleton: DashboardWidgetSkeleton::List,
            ),
            layout: new DashboardWidgetLayout(
                defaultOrder: 190,
                defaultSize: DashboardWidgetSize::Medium,
                supportedSizes: [DashboardWidgetSize::Small, DashboardWidgetSize::Medium],
            ),
            access: DashboardWidgetAccess::authenticated(),
        );
    }

    /**
     * Nacita prihlaseni vlastnika dashboardu a lokalizuje zpusob overeni
     */
    public function load(
        DashboardWidgetContext $context,
        DashboardWidgetDefinition $definition,
    ): DashboardWidgetContent {
        unset($definition);

        $events = $this->audit->recentLoginEvents($context->ownerKey(), 5);
        if ($events === []) {
            return DashboardWidgetContent::empty();
        }

        $items = [];
        foreach ($events as $event) {
            $method = $event['payload']['method'] ?? null;
            $method = in_array($method, ['local', 'oidc'], true) ? $method : 'unknown';
            $provider = $event['payload']['provider'] ?? null;
            $items[] = [
                'createdAt' => $event['created_at'],
                'method' => $this->translator->get('audit.widgets.my_logins.methods.' . $method),
                'provider' => is_string($provider) && $provider !== '' ? $provider : null,
            ];
        }

        return DashboardWidgetContent::ready(['items' => $items]);
    }
}
