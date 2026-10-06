<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Oznamuje selhani physical cleanupu az po uspesnem commitu metadata mutace.
 */
final class AdminFilePhysicalCleanupException extends \RuntimeException {}
