<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\Dashboard\Exception\DashboardWidgetApiException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;

/**
 * Poskytuje data dashboardovych widgetu
 */
final class DashboardWidgetApiService
{
    /**
     * Nastavi sluzby pro dashboardove API
     */
    public function __construct(
        private readonly DashboardWidgetRegistry $widgets,
        private readonly DashboardWidgetAccessPolicy $access,
        private readonly DashboardLayoutService $layout,
        private readonly TranslatorInterface $translator,
        private readonly ClientTranslationVersion $translationVersions,
        private readonly Router $router,
    ) {}

    /**
     * Vrati rozlozeni, katalog a preklady dostupnych widgetu
     *
     * @return array{layout:list<array<string, mixed>>,catalog:list<array<string, mixed>>,translationResources:list<array{namespace:string,source:string,versions:array<string,string>}>}
     */
    public function dashboard(DashboardWidgetContext $context): array
    {
        $layout = $this->layoutForContext($context);
        $resolvedByCode = [];
        foreach ($layout as $widget) {
            $resolvedByCode[$widget->registration()->definition()->code()] = $widget;
        }

        $catalog = [];
        foreach ($this->widgets->all() as $registration) {
            $definition = $registration->definition();
            if (!$this->access->canView($definition)) {
                continue;
            }
            $resolved = $resolvedByCode[$definition->code()] ?? null;
            $presentation = $definition->presentation();
            $catalog[] = [
                'code' => $definition->code(),
                'translationGroup' => $presentation->translationGroup(),
                'titleKey' => $presentation->titleKey(),
                'title' => $this->translator->get($presentation->titleKey()),
                'descriptionKey' => $presentation->descriptionKey(),
                'icon' => $presentation->icon()?->value,
                'pinned' => $resolved !== null,
                'managed' => false,
            ];
        }

        return [
            'layout' => array_map(fn(DashboardResolvedWidget $widget): array => $this->layoutItem($widget), $layout),
            'catalog' => $catalog,
            'translationResources' => $this->translationResources($layout, $catalog),
        ];
    }

    /**
     * Vrati obsah pripnuteho a dostupneho widgetu
     *
     * @return array{code:string,translationGroup:string,state:string,contentView:string,emptyMessageKey:string|null,viewData:array<string,mixed>,footer:array{labelKey:string,routeName:string,routeParameters:array<string,bool|float|int|string|null>}|null}
     */
    public function content(DashboardWidgetContext $context, string $widgetCode): array
    {
        $registration = $this->widgets->registration($widgetCode);
        if ($registration === null) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_NOT_FOUND,
                HttpStatusCode::NOT_FOUND,
            );
        }
        if (!$this->access->canView($registration->definition())) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_FORBIDDEN,
                HttpStatusCode::FORBIDDEN,
            );
        }
        if ($this->resolvedWidget($context, $widgetCode) === null) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_NOT_PINNED,
                HttpStatusCode::FORBIDDEN,
            );
        }

        $content = $registration->provider()->load($context, $registration->definition());
        $footer = $content->footerAction();

        $presentation = $registration->definition()->presentation();

        return [
            'code' => $widgetCode,
            'translationGroup' => $presentation->translationGroup(),
            'state' => $content->state()->value,
            'contentView' => $presentation->contentView(),
            'emptyMessageKey' => $presentation->emptyMessageKey(),
            'viewData' => $content->viewData(),
            'footer' => $footer === null ? null : [
                'labelKey' => $footer->labelKey(),
                'routeName' => $footer->routeName(),
                'routeParameters' => $footer->routeParameters(),
            ],
        ];
    }

    /**
     * Pripne dostupny widget na konec rozlozeni
     */
    public function pin(DashboardWidgetContext $context, string $widgetCode): void
    {
        try {
            $this->layout->pin($context, $widgetCode, $this->nextPosition($context));
        } catch (\InvalidArgumentException) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_NOT_AVAILABLE,
                HttpStatusCode::NOT_FOUND,
            );
        }
    }

    /**
     * Odebere pripnuty widget z rozlozeni
     */
    public function unpin(DashboardWidgetContext $context, string $widgetCode): void
    {
        $widget = $this->resolvedWidget($context, $widgetCode);
        if ($widget === null) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_NOT_PINNED,
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
        $this->layout->unpin($context, $widgetCode);
    }

    /**
     * Ulozi nove poradi pripnutych widgetu
     *
     * @param list<string> $widgetCodes
     */
    public function reorder(DashboardWidgetContext $context, array $widgetCodes): void
    {
        try {
            $this->layout->reorder($context, $widgetCodes);
        } catch (\InvalidArgumentException) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_ORDER_INVALID,
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * Zmeni velikost pripnuteho widgetu
     */
    public function resize(DashboardWidgetContext $context, string $widgetCode, string $size): void
    {
        $resolved = $this->resolvedWidget($context, $widgetCode);
        if ($resolved === null) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_NOT_PINNED,
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
        $widgetSize = DashboardWidgetSize::tryFrom($size);
        if ($widgetSize === null) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_SIZE_INVALID,
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $this->layout->updateSize($context, $widgetCode, $widgetSize);
        } catch (\InvalidArgumentException) {
            throw new DashboardWidgetApiException(
                AdminErrorCode::DASHBOARD_WIDGET_SIZE_INVALID,
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * Vrati dalsi volnou pozici v rozlozeni
     */
    private function nextPosition(DashboardWidgetContext $context): int
    {
        $layout = $this->layoutForContext($context);
        if ($layout === []) {
            return 0;
        }

        return max(array_map(static fn(DashboardResolvedWidget $widget): int => $widget->position(), $layout)) + 1;
    }

    /**
     * Vrati pripnuty widget podle kodu
     */
    private function resolvedWidget(DashboardWidgetContext $context, string $widgetCode): DashboardResolvedWidget|null
    {
        foreach ($this->layoutForContext($context) as $widget) {
            if ($widget->registration()->definition()->code() === $widgetCode) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * Vrati rozlozeni dostupne pro kontext
     *
     * @return list<DashboardResolvedWidget>
     */
    private function layoutForContext(DashboardWidgetContext $context): array
    {
        return $this->layout->forContext($context);
    }

    /**
     * Prevede pripnuty widget na data dashboardoveho API
     *
     * @return array<string, mixed>
     */
    private function layoutItem(DashboardResolvedWidget $widget): array
    {
        $definition = $widget->registration()->definition();
        $presentation = $definition->presentation();
        $layout = $definition->layout();

        return [
            'code' => $definition->code(),
            'translationGroup' => $presentation->translationGroup(),
            'titleKey' => $presentation->titleKey(),
            'title' => $this->translator->get($presentation->titleKey()),
            'icon' => $presentation->icon()?->value,
            'size' => $widget->size()->value,
            'supportedSizes' => array_map(static fn(DashboardWidgetSize $size): string => $size->value, $layout->supportedSizes()),
            'membership' => 'personal',
            'managed' => false,
            'protection' => null,
            'removable' => true,
            'skeleton' => $presentation->skeleton()->value,
            'position' => $widget->position(),
            'contentEndpoint' => $this->router->url('admin.api.dashboard.widget', ['widgetCode' => $definition->code()]),
        ];
    }

    /**
     * Vrati zdroje klientskych prekladu potrebne pro widgety
     *
     * @param list<DashboardResolvedWidget> $layout
     * @param list<array<string,mixed>> $catalog
     * @return list<array{namespace:string,source:string,versions:array<string,string>}>
     */
    private function translationResources(array $layout, array $catalog): array
    {
        $groups = [];
        foreach ($layout as $widget) {
            $groups[$widget->registration()->definition()->presentation()->translationGroup()] = true;
        }
        foreach ($catalog as $widget) {
            $group = $widget['translationGroup'] ?? null;
            if (is_string($group)) {
                $groups[$group] = true;
            }
        }
        ksort($groups);

        return array_map(
            fn(string $group): array => [
                'namespace' => $group,
                'source' => $this->router->url('admin.resources.i18n', ['group' => $group]) . '?locale={locale}&v={version}',
                'versions' => $this->translationVersions->versions($group),
            ],
            array_keys($groups),
        );
    }
}
