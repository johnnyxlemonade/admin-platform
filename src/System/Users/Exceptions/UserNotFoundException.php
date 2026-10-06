<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Exceptions;

use RuntimeException;

/**
 * Oznamuje, ze pozadovany uzivatel neni dostupny pro Users operaci
 */
final class UserNotFoundException extends RuntimeException {}
