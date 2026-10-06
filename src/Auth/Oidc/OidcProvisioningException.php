<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use RuntimeException;

/**
 * Signalizuje bezpecne odmitnuti OIDC provisioningu
 */
final class OidcProvisioningException extends RuntimeException {}
