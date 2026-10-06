<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Http\Controller;

use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Dashboard\DashboardWidgetApiService;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\Exception\DashboardWidgetApiException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro dashboardapi
 */
final class DashboardApiController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly CurrentUserProvider $currentUser,
        private readonly DashboardWidgetApiService $widgets,
        private readonly AdminUiLocale $locale,
        private readonly ExceptionLogger $exceptions,
        private readonly ViewRendererInterface $views,
        private readonly Responses $responses,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Zpracovava krok dashboard v HTTP toku administrace
     */
    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));
        $context = $this->context($locale);
        if ($context === null) {
            return $this->unauthenticated();
        }

        $dashboard = $this->widgets->dashboard($context);

        return $this->responses->json([
            ...$dashboard,
            'widgetAreaHtml' => $this->views->content('admin::dashboard.widget-area', [
                'dashboardWidgets' => $dashboard['layout'],
            ]),
        ]);
    }

    /**
     * Zpracovava krok content v HTTP toku administrace
     */
    public function content(string $widgetCode, ServerRequestInterface $request): ResponseInterface
    {
        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));
        $context = $this->context($locale);
        if ($context === null) {
            return $this->unauthenticated();
        }

        try {
            $content = $this->widgets->content($context, $widgetCode);
            $html = $content['state'] === 'ready'
                ? $this->views->content($content['contentView'], $content['viewData'])
                : '';
            $footer = $content['footer'];

            $payload = $content;
            $payload['html'] = $html;
            $payload['footer'] = $footer === null ? null : [
                ...$footer,
                'url' => $this->urls->route($footer['routeName'], $footer['routeParameters']),
            ];

            return $this->responses->json($payload);
        } catch (DashboardWidgetApiException $exception) {
            return $this->failure($exception);
        } catch (\Throwable $exception) {
            $this->exceptions->log($exception, 'dashboard-widget-content');

            return $this->responses->json([
                'success' => false,
                'error' => ['code' => AdminErrorCode::DASHBOARD_WIDGET_CONTENT_FAILED->value],
            ], HttpStatusCode::INTERNAL_SERVER_ERROR->value);
        }
    }

    /**
     * Zpracovava krok action v HTTP toku administrace
     */
    public function action(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $locale = $this->locale->activate(null, (string) $requestData->cookie('lemonade_locale', ''));
        $context = $this->context($locale);
        if ($context === null) {
            return $this->unauthenticated();
        }
        $payload = $requestData->jsonPayload();
        $action = $payload['action'] ?? null;
        $widgetCode = $payload['widgetCode'] ?? null;

        try {
            if ($action === 'pin' && is_string($widgetCode)) {
                $this->widgets->pin($context, $widgetCode);
            } elseif ($action === 'unpin' && is_string($widgetCode)) {
                $this->widgets->unpin($context, $widgetCode);
            } elseif ($action === 'resize' && is_string($widgetCode) && is_string($payload['size'] ?? null)) {
                $this->widgets->resize($context, $widgetCode, $payload['size']);
            } elseif ($action === 'reorder' && is_array($payload['widgetCodes'] ?? null)) {
                $widgetCodes = [];
                foreach ($payload['widgetCodes'] as $code) {
                    if (!is_string($code)) {
                        return $this->responses->json([
                            'success' => false,
                            'error' => ['code' => AdminErrorCode::DASHBOARD_WIDGET_ORDER_INVALID->value],
                        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
                    }
                    $widgetCodes[] = $code;
                }
                $this->widgets->reorder($context, $widgetCodes);
            } else {
                return $this->responses->json([
                    'success' => false,
                    'error' => ['code' => AdminErrorCode::DASHBOARD_WIDGET_ACTION_INVALID->value],
                ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
            }
        } catch (DashboardWidgetApiException $exception) {
            return $this->failure($exception);
        }

        $dashboard = $this->widgets->dashboard($context);

        return $this->responses->json([
            'success' => true,
            ...$dashboard,
            'widgetAreaHtml' => $this->views->content('admin::dashboard.widget-area', [
                'dashboardWidgets' => $dashboard['layout'],
            ]),
        ]);
    }

    /**
     * Zpracovava krok context v HTTP toku administrace
     */
    private function context(string $locale): ?DashboardWidgetContext
    {
        $user = $this->currentUser->currentUser();
        if ($user !== null) {
            return DashboardWidgetContext::forLocalUser($user, $locale);
        }

        return null;
    }

    /**
     * Zpracovava krok unauthenticated v HTTP toku administrace
     */
    private function unauthenticated(): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'error' => ['code' => AdminErrorCode::UNAUTHENTICATED->value],
        ], HttpStatusCode::UNAUTHORIZED->value);
    }

    /**
     * Zpracovava krok failure v HTTP toku administrace
     */
    private function failure(DashboardWidgetApiException $exception): ResponseInterface
    {
        return $this->responses->json(['success' => false, 'error' => ['code' => $exception->errorCode()]], $exception->status());
    }
}
