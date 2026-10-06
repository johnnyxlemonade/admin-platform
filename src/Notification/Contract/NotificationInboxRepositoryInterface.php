<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification\Contract;

use Lemonade\Admin\Notification\NotificationRecipientIdentity;

/**
 * Urcuje persistence operace nad osobnim inboxem administracnich oznameni
 */
interface NotificationInboxRepositoryInterface
{
    /**
     * Vrati jednu stranku oznameni viditelnych pro daneho prijemce
     *
     * @return array{rows:list<array{id:int,type:string,title:string,message:string,created_at:string,read_at:string|null,author_first_name:string|null,author_last_name:string|null,author_email:string|null}>,hasMore:bool}
     */
    public function inboxPageForRecipient(NotificationRecipientIdentity $recipient, int $page, int $perPage): array;

    /**
     * Spocita neprectena oznameni viditelna pro daneho prijemce
     */
    public function unreadCountForRecipient(NotificationRecipientIdentity $recipient): int;

    /**
     * Overi, zda ma prijemce alespon jedno neprectene viditelne oznameni
     */
    public function hasUnreadForRecipient(NotificationRecipientIdentity $recipient): bool;

    /**
     * Oznaci jedno viditelne oznameni prijemce jako prectene
     */
    public function markReadForRecipient(int $notificationId, NotificationRecipientIdentity $recipient): bool;

    /**
     * Oznaci vsechna viditelna oznameni prijemce jako prectena
     */
    public function markAllReadForRecipient(NotificationRecipientIdentity $recipient): int;
}
