<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Http\Controller;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Admin\System\Modules\ModulesFeaturesPageProvider;
use Lemonade\Framework\Core\Http\RequestData;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje autorizovane zobrazeni feature management stranky modulu
 */
final class ModulesFeaturesController
{
    /**
     * Nastavuje page projection a sdilene Admin HTTP zavislosti
     */
    public function __construct(
        private readonly ModulesFeaturesPageProvider $pages,
        private readonly AuthorizationService $authorization,
        private readonly AdminUiLocale $locale,
        private readonly AdminResponseFactory $responses,
        private readonly AdminPageRenderer $pageRenderer,
    ) {}

    /**
     * Overuje pravo zobrazeni a renderuje existujici feature projection
     */
    public function show(string $module, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorization->hasPermission('system.modules.view')) {
            return $this->responses->authorizationDenied($request);
        }

        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));
        $page = $this->pages->page($module, $locale, $this->authorization->hasPermission('system.modules.manage_features'));
        if ($page === null) {
            return $this->responses->notFound($request);
        }

        return $this->pageRenderer->render($page->view(), ['title' => $page->title(), ...$page->data()]);
    }
}
