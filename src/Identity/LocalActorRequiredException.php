<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

use RuntimeException;

final class LocalActorRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A local user is required.');
    }
}
