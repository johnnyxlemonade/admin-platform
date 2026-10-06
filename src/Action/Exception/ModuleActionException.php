<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Exception;

use Lemonade\Admin\Editor\Lock\EditorLockOwner;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Popisuje chybu admin akce pro JSON odpoved
 */
final class ModuleActionException extends RuntimeException
{
    /**
     * Vytvori chybu se stavem odpovedi a kodem klienta
     */
    public function __construct(
        private readonly HttpStatusCode $status,
        private readonly AdminErrorCode $errorCode,
        string $message,
        private readonly ?EditorLockOwner $lockedBy = null,
        private readonly bool $recordConflict = false,
    ) {
        parent::__construct($message);
    }

    /**
     * Vrati HTTP stav chyby jako cislo
     */
    public function status(): int
    {
        return $this->status->value;
    }

    /**
     * Vrati kod chyby pro admin klienta
     */
    public function errorCode(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Vrati HTTP stav chyby
     */
    public function statusCode(): HttpStatusCode
    {
        return $this->status;
    }

    /**
     * Vrati kod chyby pro admin klienta
     */
    public function error(): AdminErrorCode
    {
        return $this->errorCode;
    }

    /**
     * Vrati vlastnika konfliktniho zamku pokud existuje
     */
    public function lockedBy(): ?EditorLockOwner
    {
        return $this->lockedBy;
    }

    /**
     * Urci zda chyba oznacuje konflikt zaznamu
     */
    public function isRecordConflict(): bool
    {
        return $this->recordConflict;
    }
}
