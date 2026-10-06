<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Exception;

use RuntimeException;
use Throwable;

/**
 * Oznamuje chybu pri editorvalidation
 */
final class EditorValidationException extends RuntimeException
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(private readonly string $messageKey, ?Throwable $previous = null)
    {
        parent::__construct($messageKey, 0, $previous);
    }

    /**
     * Zpracovava hodnotu messagekey v konfiguraci editoru
     */
    public function messageKey(): string
    {
        return $this->messageKey;
    }
}
