<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Exception;

use RuntimeException;
use Throwable;

/**
 * Vraci obecnou chybu nebo lokalizovane chyby konkretnich poli editoru
 */
final class EditorValidationException extends RuntimeException
{
    /**
     * Nastavuje obecny prekladovy klic a volitelne hotove zpravy pod jmeny poli
     *
     * @param array<string,string> $errors
     */
    public function __construct(
        private readonly string $messageKey,
        ?Throwable $previous = null,
        private readonly array $errors = [],
    ) {
        parent::__construct($messageKey, 0, $previous);
    }

    /**
     * Zpracovava hodnotu messagekey v konfiguraci editoru
     */
    public function messageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * Vraci lokalizovane chyby jednotlivych poli
     *
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
