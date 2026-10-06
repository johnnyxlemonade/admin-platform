<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action;

use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Nastavuje parametry admin akce modulu
 */
final readonly class ModuleActionDefinition
{
    /**
     * Vytvori akci s opravnenim, popiskem a volitelnym potvrzenim
     */
    public function __construct(
        private string $key,
        private string $permission,
        private string $labelKey,
        private ?ConfirmationDefinition $confirmation = null,
        private bool $refreshGrid = false,
    ) {}

    /**
     * Vrati unikatni klic akce
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Vrati opravneni potrebne pro spusteni akce
     */
    public function permission(): string
    {
        return $this->permission;
    }

    /**
     * Vrati prekladovy klic popisku akce
     */
    public function labelKey(): string
    {
        return $this->labelKey;
    }

    /**
     * Vrati volitelne potvrzeni pred spustenim akce
     */
    public function confirmation(): ?ConfirmationDefinition
    {
        return $this->confirmation;
    }

    /**
     * Urci zda se ma po akci obnovit tabulka
     */
    public function refreshGrid(): bool
    {
        return $this->refreshGrid;
    }
}
