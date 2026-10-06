<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\Notification\Contract\NotificationInboxRepositoryInterface;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Poskytuje osobni inbox a stav neprectenych administracnich oznameni
 */
final class NotificationService
{
    /**
     * Nastavuje zdroje inboxu, identity prijemce a presentation dat
     */
    public function __construct(
        private readonly NotificationInboxRepositoryInterface $notifications,
        private readonly NotificationRecipientIdentityResolver $recipients,
        private readonly TranslatorInterface $translator,
        private readonly NotificationPresentation $presentation,
    ) {}

    /**
     * Vrati stranku osobniho inboxu vcetne poctu neprectenych oznameni
     *
     * @return array{items:list<array{id:int,type:string,title:string,message:string,author:string,icon:string,createdAt:string,read:bool}>,unread:int,pagination:array{page:int,perPage:int,hasMore:bool}}
     */
    public function inbox(int $page = 1, int $perPage = 20): array
    {
        $recipient = $this->recipients->current();
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));
        if ($recipient === null) {
            return [
                'items' => [],
                'unread' => 0,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'hasMore' => false,
                ],
            ];
        }

        $inbox = $this->notifications->inboxPageForRecipient($recipient, $page, $perPage);
        $items = array_map(function (array $row): array {
            $type = NotificationType::tryFrom((string) $row['type']) ?? NotificationType::Info;
            return [
                'id' => (int) $row['id'],
                'type' => $type->value,
                'title' => (string) $row['title'],
                'message' => (string) $row['message'],
                'author' => $this->presentation->author($row, $this->translator),
                'icon' => $type->icon()->value,
                'createdAt' => str_replace(' ', 'T', (string) $row['created_at']),
                'read' => $row['read_at'] !== null,
            ];
        }, $inbox['rows']);

        return [
            'items' => $items,
            'unread' => $this->notifications->unreadCountForRecipient($recipient),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'hasMore' => $inbox['hasMore'],
            ],
        ];
    }

    /**
     * Overi, zda ma aktualni prijemce alespon jedno neprectene oznameni
     */
    public function hasUnread(): bool
    {
        $recipient = $this->recipients->current();

        return $recipient !== null && $this->notifications->hasUnreadForRecipient($recipient);
    }

    /**
     * Oznaci viditelne oznameni aktualniho prijemce jako prectene
     */
    public function markRead(int $id): bool
    {
        $recipient = $this->recipients->current();
        return $recipient !== null && $this->notifications->markReadForRecipient($id, $recipient);
    }

    /**
     * Oznaci vsechna viditelna oznameni aktualniho prijemce jako prectena
     */
    public function markAllRead(): int
    {
        $recipient = $this->recipients->current();
        return $recipient === null ? 0 : $this->notifications->markAllReadForRecipient($recipient);
    }
}
