<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

/**
 * Mapuje administracni akci na deaktivaci uzivatele
 */
final class UsersDeactivateAction extends UsersSetActiveAction
{
    protected function active(): bool
    {
        return false;
    }

    protected function messageKey(): string
    {
        return 'users.actions.deactivated';
    }
}
