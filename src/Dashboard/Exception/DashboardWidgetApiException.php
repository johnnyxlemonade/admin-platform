<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Exception;

use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Oznamuje chybu API dashboardovych widgetu
 */
final class DashboardWidgetApiException extends RuntimeException
{
    /**
     * Vytvori chybu API s kodem pro klienta a HTTP stavem
     */
    public function __construct(
        private readonly AdminErrorCode $error,
        private readonly HttpStatusCode $statusCode,
    ) {
        parent::__construct($error->value);
    }

    /**
     * Vrati kod chyby pro klienta
     */
    public function errorCode(): string
    {
        return $this->error->value;
    }

    /**
     * Vrati HTTP stav chyby jako cislo
     */
    public function status(): int
    {
        return $this->statusCode->value;
    }

    /**
     * Vrati kod chyby API
     */
    public function error(): AdminErrorCode
    {
        return $this->error;
    }

    /**
     * Vrati HTTP stav chyby
     */
    public function statusCode(): HttpStatusCode
    {
        return $this->statusCode;
    }
}
