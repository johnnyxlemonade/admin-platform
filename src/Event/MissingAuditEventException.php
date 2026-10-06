<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

use RuntimeException;

/**
 * Oznamuje chybejici auditni udalost
 */
final class MissingAuditEventException extends RuntimeException {}
