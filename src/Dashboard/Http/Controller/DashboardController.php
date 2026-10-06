<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Http\Controller;

use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Dashboard\DashboardWidgetApiService;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro dashboard
 */
final class DashboardController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly CurrentUserProvider $currentUser,
        private readonly DashboardWidgetApiService $widgets,
        private readonly AdminUiLocale $locale,
        private readonly TranslatorInterface $translator,
        private readonly AdminPageRenderer $pages,
    ) {}

    /**
     * Vykresluje nebo vraci data pozadovane administracni stranky
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $locale = $this->locale->activate(null, (string) (new \Lemonade\Framework\Core\Http\RequestData($request))->cookie('lemonade_locale', ''));
        $user = $this->currentUser->currentUser();
        $context = $user === null ? null : DashboardWidgetContext::forLocalUser($user, $locale);
        $dashboard = $context === null ? ['layout' => [], 'translationResources' => []] : $this->widgets->dashboard($context);

        return $this->pages->render('admin::dashboard', [
            'title' => $this->translator->get('admin.dashboard.title'),
            'locale' => $locale,
            'dashboardWidgets' => $dashboard['layout'],
            'widgetTranslationResources' => $dashboard['translationResources'],
        ]);
    }
}
