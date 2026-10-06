<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Exceptions;

use RuntimeException;

/**
 * Oznamuje chybejici cilovou roli pri CRUD operaci
 */
final class RoleNotFoundException extends RuntimeException {}
