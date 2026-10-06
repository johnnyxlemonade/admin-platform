<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Vymezuje podporovane typy administracnich oznameni
 */
enum NotificationType: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Important = 'important';

    /**
     * Zpracovava hodnotu icon pro oznameni
     */
    public function icon(): AdminIcon
    {
        return match ($this) {
            self::Info => AdminIcon::InfoCircle,self::Success => AdminIcon::CheckCircle,self::Warning => AdminIcon::ExclamationTriangle,self::Important => AdminIcon::ExclamationCircle,
        };
    }
}
