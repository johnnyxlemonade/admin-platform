<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Popisuje data administracniho oznameni
 */
final class NotificationPresentation
{
    /**
     * Zpracovava hodnotu author pro oznameni
     * @param array<string, mixed> $notification
     */
    public function author(array $notification, TranslatorInterface $translator): string
    {
        $name = trim((string) ($notification['author_first_name'] ?? '') . ' ' . (string) ($notification['author_last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = (string) ($notification['author_email'] ?? '');
        if ($email !== '') {
            return $email;
        }
        return $translator->get('admin.notifications.actor.unknownUser');
    }
}
