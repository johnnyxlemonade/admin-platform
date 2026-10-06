<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Http\Controller;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\AuthorizationTargetPolicy;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Poskytuje autorizovane asynchronni volby aktivnich prijemcu pro management editor
 */
final class NotificationsAudienceOptionsController
{
    private const PAGE_SIZE = 25;
    private const CANDIDATE_BATCH_SIZE = 100;

    public function __construct(
        private readonly CurrentUserProvider $current,
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationTargetPolicy $targets,
        private readonly NotificationModel $notifications,
        private readonly Responses $responses,
    ) {}

    /**
     * Filtruje aktivni uzivatele podle cilove authorization a strankuje az schvalene volby
     */
    public function users(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->current->currentUser();
        if ($actor === null || !$this->authorization->can($actor, 'system.notifications.publish')) {
            return $this->responses->json([
                'error' => ['code' => AdminErrorCode::FORBIDDEN->value],
            ], HttpStatusCode::FORBIDDEN->value);
        }

        $requestData = new RequestData($request);
        $search = trim((string) $requestData->query('q', ''));
        $page = max(1, (int) $requestData->query('page', 1));
        $required = ($page * self::PAGE_SIZE) + 1;
        $authorized = [];
        $offset = 0;
        do {
            $candidates = $this->notifications->searchActiveUsers($search, self::CANDIDATE_BATCH_SIZE, $offset);
            foreach ($candidates as $user) {
                if ($this->targets->canTargetUser($actor, new AuthenticatedUser((int) $user['id'], (string) $user['email']))) {
                    $authorized[] = $user;
                    if (count($authorized) >= $required) {
                        break 2;
                    }
                }
            }
            $offset += count($candidates);
        } while (count($candidates) === self::CANDIDATE_BATCH_SIZE);

        $start = ($page - 1) * self::PAGE_SIZE;
        $pageUsers = array_slice($authorized, $start, self::PAGE_SIZE);
        $items = array_map(static function (array $user): array {
            $label = trim($user['first_name'] . ' ' . $user['last_name']);

            return ['value' => (string) $user['id'], 'label' => $label !== '' ? $label . ' (' . $user['email'] . ')' : $user['email']];
        }, $pageUsers);

        return $this->responses->json(['items' => $items, 'pagination' => ['page' => $page, 'perPage' => self::PAGE_SIZE, 'hasMore' => count($authorized) > $start + self::PAGE_SIZE]]);
    }
}
