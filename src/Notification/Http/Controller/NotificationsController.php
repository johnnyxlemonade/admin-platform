<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification\Http\Controller;

use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Notification\NotificationInboxPagination;
use Lemonade\Admin\Notification\NotificationService;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro notifications
 */
final class NotificationsController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly NotificationService $notifications,
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

        return $this->pages->render('admin::notifications', [
            'title' => $this->translator->get('admin.notifications.title'),
            'notifications' => $this->notifications->inbox(1, NotificationInboxPagination::PER_PAGE),
            'inboxPerPage' => NotificationInboxPagination::PER_PAGE,
            'locale' => $locale,
        ]);
    }
}
