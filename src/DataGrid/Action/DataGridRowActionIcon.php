<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Prirazuje sdilene klice akci k ikonam administrace
 */
final class DataGridRowActionIcon
{
    /**
     * Vrati ikonu odpovidajici sdilenemu klici akce
     */
    public static function forKey(string $key): ?AdminIcon
    {
        return match ($key) {
            'edit' => AdminIcon::PencilSquare,
            'enable', 'activate' => AdminIcon::CheckCircle,
            'disable', 'deactivate' => AdminIcon::PauseCircle,
            'delete', 'remove' => AdminIcon::Trash3,
            'restore' => AdminIcon::ArrowCounterclockwise,
            'reset-display' => AdminIcon::ArrowCounterclockwise,
            'set-default' => AdminIcon::Star,
            'open', 'detail' => AdminIcon::BoxArrowUpRight,
            'view' => AdminIcon::Eye,
            'features', 'feature-toggle' => AdminIcon::Sliders,
            'settings' => AdminIcon::Gear,
            'install' => AdminIcon::Boxes,
            default => null,
        };
    }
}
