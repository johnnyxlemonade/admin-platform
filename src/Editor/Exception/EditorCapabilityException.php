<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Exception;

use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Oznamuje chybu pri editorcapability
 */
final class EditorCapabilityException extends RuntimeException
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private readonly HttpStatusCode $status,
        private readonly AdminErrorCode $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Zpracovava hodnotu status v konfiguraci editoru
     */
    public function status(): int
    {
        return $this->status->value;
    }

    /**
     * Zpracovava hodnotu errorcode v konfiguraci editoru
     */
    public function errorCode(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Zpracovava hodnotu statuscode v konfiguraci editoru
     */
    public function statusCode(): HttpStatusCode
    {
        return $this->status;
    }

    /**
     * Zpracovava hodnotu error v konfiguraci editoru
     */
    public function error(): AdminErrorCode
    {
        return $this->errorCode;
    }
}
