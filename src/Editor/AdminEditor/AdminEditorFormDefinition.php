<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Popisuje nemennou konfiguraci admineditorform
 */
final readonly class AdminEditorFormDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private string $id,
        private string $action,
        private string $method = 'POST',
        private ?string $actionUrl = null,
        private ?string $actionKey = null,
        private bool $csrf = true,
        private bool $novalidate = false,
        private bool $navigateToEditAfterCreate = false,
    ) {
        foreach (['Form ID' => $id, 'Form action' => $action, 'Form method' => $method] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException($label . ' must not be empty.');
            }
        }
    }

    /**
     * Zpracovava hodnotu id v konfiguraci editoru
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Zpracovava hodnotu action v konfiguraci editoru
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Zpracovava hodnotu method v konfiguraci editoru
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Zpracovava hodnotu actionurl v konfiguraci editoru
     */
    public function actionUrl(): ?string
    {
        return $this->actionUrl;
    }

    /**
     * Zpracovava hodnotu actionkey v konfiguraci editoru
     */
    public function actionKey(): ?string
    {
        return $this->actionKey;
    }

    /**
     * Zpracovava hodnotu csrf v konfiguraci editoru
     */
    public function csrf(): bool
    {
        return $this->csrf;
    }

    /**
     * Zpracovava hodnotu novalidate v konfiguraci editoru
     */
    public function novalidate(): bool
    {
        return $this->novalidate;
    }

    /**
     * Zpracovava hodnotu navigatetoeditaftercreate v konfiguraci editoru
     */
    public function navigateToEditAfterCreate(): bool
    {
        return $this->navigateToEditAfterCreate;
    }
}
