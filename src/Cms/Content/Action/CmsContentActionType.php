<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content\Action;

enum CmsContentActionType: string
{
    case ChangeUrl = 'change-url';
    case Delete = 'delete';
    case Restore = 'restore';
    case AssignOwner = 'assign-owner';
}
