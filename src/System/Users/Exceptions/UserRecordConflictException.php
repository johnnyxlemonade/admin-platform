<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Exceptions;

use RuntimeException;

/**
 * Oznamuje optimistic-lock konflikt pri ulozeni uzivatelskeho editoru
 */
final class UserRecordConflictException extends RuntimeException {}
