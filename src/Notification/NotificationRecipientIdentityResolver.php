<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Framework\Database\Database;

/**
 * Popisuje data administracniho oznameni
 */
class NotificationRecipientIdentityResolver
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani oznameni
     */
    public function __construct(private readonly CurrentUserProvider $current, private readonly Database $database) {}

    /**
     * Zpracovava hodnotu current pro oznameni
     */
    public function current(): ?NotificationRecipientIdentity
    {
        $user = $this->current->currentUser();
        if ($user !== null) {
            $roles = $this->database->select('SELECT r.code FROM system_user_role ur JOIN system_role r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.deleted_at IS NULL ORDER BY r.code', [$user->id()]);

            return NotificationRecipientIdentity::local($user, array_map(static fn(array $role): string => (string) $role['code'], $roles));
        }

        return null;
    }
}
