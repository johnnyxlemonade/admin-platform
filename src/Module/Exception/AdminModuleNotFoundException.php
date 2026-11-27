<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module\Exception;

use RuntimeException;

/**
 * Reports an unknown module code or route segment after successful catalog loading
 */
final class AdminModuleNotFoundException extends RuntimeException {}
