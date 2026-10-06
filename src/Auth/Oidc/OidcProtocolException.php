<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use RuntimeException;

/**
 * Oznamuje chybu protokolu overeni identity
 */
final class OidcProtocolException extends RuntimeException {}
