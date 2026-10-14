<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Nastavuje akci dostupnou na radku datove tabulky
 */
final readonly class DataGridRowActionDefinition
{
    /**
     * Vytvori akci radku s volitelnym potvrzenim a zobrazenim
     */
    public function __construct(
        private string $key,
        private string $label,
        private string $url,
        private string $method,
        private ?ConfirmationDefinition $confirmation = null,
        private ?string $modalUrl = null,
        private ?string $modalSize = null,
        private ?DataGridRowActionKind $kind = null,
        private ?DataGridRowActionPlacement $placement = null,
        private ?DataGridRowActionRisk $risk = null,
        private ?bool $refresh = null,
        private ?string $ariaLabel = null,
        private ?AdminIcon $icon = null,
    ) {}

    /**
     * Vrati akci s pristupnym popiskem
     */
    public function withAriaLabel(string $ariaLabel): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            url: $this->url,
            method: $this->method,
            confirmation: $this->confirmation,
            modalUrl: $this->modalUrl,
            modalSize: $this->modalSize,
            kind: $this->kind,
            placement: $this->placement,
            risk: $this->risk,
            refresh: $this->refresh,
            ariaLabel: $ariaLabel,
            icon: $this->icon,
        );
    }

    /**
     * Vrati akci s ikonou
     */
    public function withIcon(AdminIcon $icon): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            url: $this->url,
            method: $this->method,
            confirmation: $this->confirmation,
            modalUrl: $this->modalUrl,
            modalSize: $this->modalSize,
            kind: $this->kind,
            placement: $this->placement,
            risk: $this->risk,
            refresh: $this->refresh,
            ariaLabel: $this->ariaLabel,
            icon: $icon,
        );
    }

    /**
     * Vrati akci ve formatu DataGrid JSON transportu
     *
     * @return array{key:string,label:string,url:string,method:string,confirm:string|null,confirmTitle?:string,ariaLabel?:string,icon?:string,modalUrl?:string,modalSize?:string|null,kind?:string,placement?:string,risk?:string,refresh?:bool}
     */
    public function toArray(): array
    {
        $action = [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->url,
            'method' => $this->method,
            'confirm' => $this->confirmation?->messageKey(),
        ];
        if ($this->modalUrl !== null) {
            $action['modalUrl'] = $this->modalUrl;
            $action['modalSize'] = $this->modalSize;
        }
        $confirmationTitle = $this->confirmation?->titleKey();
        if ($confirmationTitle !== null) {
            $action['confirmTitle'] = $confirmationTitle;
        }
        if ($this->ariaLabel !== null) {
            $action['ariaLabel'] = $this->ariaLabel;
        }
        if ($this->icon !== null) {
            $action['icon'] = $this->icon->value;
        }
        if ($this->kind !== null) {
            $action['kind'] = $this->kind->value;
        }
        if ($this->placement !== null) {
            $action['placement'] = $this->placement->value;
        }
        if ($this->risk !== null) {
            $action['risk'] = $this->risk->value;
        }
        if ($this->refresh !== null) {
            $action['refresh'] = $this->refresh;
        }

        return $action;
    }
}
