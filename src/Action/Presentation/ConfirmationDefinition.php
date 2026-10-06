<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Presentation;

/**
 * Nastavuje potvrzeni zobrazene pred spustenim akce
 */
final readonly class ConfirmationDefinition
{
    /**
     * Vytvori potvrzeni s textem a volitelnym nadpisem
     */
    public function __construct(
        private string $messageKey,
        private ?string $titleKey = null,
    ) {}

    /**
     * Vrati klic textu potvrzeni
     */
    public function messageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * Vrati klic nadpisu potvrzeni pokud je nastaveny
     */
    public function titleKey(): ?string
    {
        return $this->titleKey;
    }
}
