<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Exceptions;

use RuntimeException;

/**
 * Oznamuje zamitnuti autorizovane operace nad roli
 */
final class RoleAuthorizationException extends RuntimeException {}
