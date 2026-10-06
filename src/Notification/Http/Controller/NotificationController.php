<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification\Http\Controller;

use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Notification\NotificationInboxPagination;
use Lemonade\Admin\Notification\NotificationService;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Poskytuje JSON inbox, indikaci neprectenych oznameni a read mutace
 */
final class NotificationController
{
    /**
     * Nastavuje sluzbu inboxu, lokalizaci a JSON odpovedi
     */
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AdminUiLocale $locale,
        private readonly Responses $responses,
    ) {}

    /**
     * Vrati pozadovanou stranku osobniho inboxu
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $this->locale->activate(null, (string) $requestData->cookie('lemonade_locale', ''));

        $requestedPage = $requestData->query('page', 1);
        $requestedPerPage = $requestData->query('perPage', 20);
        $page = is_numeric($requestedPage) ? (int) $requestedPage : 1;
        $perPage = is_numeric($requestedPerPage) ? (int) $requestedPerPage : 20;

        return $this->responses->json($this->notifications->inbox(max(1, $page), max(1, min($perPage, NotificationInboxPagination::PER_PAGE))));
    }

    /**
     * Vrati stav indikace neprectenych oznameni aktualniho uzivatele
     */
    public function indicator(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $this->locale->activate(null, (string) $requestData->cookie('lemonade_locale', ''));

        return $this->responses->json(['hasUnread' => $this->notifications->hasUnread()]);
    }

    /**
     * Zpracuje oznaceni jednoho nebo vsech viditelnych oznameni jako prectenych
     */
    public function action(ServerRequestInterface $request): ResponseInterface
    {
        $payload = (new RequestData($request))->jsonPayload();
        $action = $payload['action'] ?? null;
        if ($action === 'mark-all-read') {
            $this->notifications->markAllRead();

            return $this->responses->json(['success' => true, 'unread' => 0]);
        }
        if ($action === 'mark-read' && isset($payload['id']) && is_numeric($payload['id'])) {
            $read = $this->notifications->markRead((int) $payload['id']);

            return $this->responses->json(['success' => $read]);
        }

        return $this->responses->json([
            'success' => false,
            'error' => ['code' => AdminErrorCode::NOTIFICATION_ACTION_INVALID->value],
        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }
}
