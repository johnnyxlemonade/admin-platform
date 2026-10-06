<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Lifecycle;

use RuntimeException;

/**
 * Oznamuje chybu v zivotnim cyklu modulu
 */
final class ModuleLifecycleException extends RuntimeException {}
