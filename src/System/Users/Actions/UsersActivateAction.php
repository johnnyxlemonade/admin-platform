<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

/**
 * Mapuje administracni akci na aktivaci uzivatele
 */
final class UsersActivateAction extends UsersSetActiveAction
{
    protected function active(): bool
    {
        return true;
    }

    protected function messageKey(): string
    {
        return 'users.actions.activated';
    }
}
