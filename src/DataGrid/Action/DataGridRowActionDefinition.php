<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Nastavuje akci dostupnou na radku datove tabulky
 */
final class DataGridRowActionDefinition
{
    private ?string $ariaLabel = null;

    private ?AdminIcon $icon = null;

    /**
     * Vytvori akci radku s volitelnym potvrzenim a zobrazenim
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $url,
        private readonly string $method,
        private readonly ?ConfirmationDefinition $confirmation = null,
        private readonly ?string $modalUrl = null,
        private readonly ?string $modalSize = null,
        private readonly ?DataGridRowActionKind $kind = null,
        private readonly ?DataGridRowActionPlacement $placement = null,
        private readonly ?DataGridRowActionRisk $risk = null,
        private readonly ?bool $refresh = null,
    ) {}

    /**
     * Vrati akci s pristupnym popiskem
     */
    public function withAriaLabel(string $ariaLabel): self
    {
        $action = clone $this;
        $action->ariaLabel = $ariaLabel;

        return $action;
    }

    /**
     * Vrati akci s ikonou
     */
    public function withIcon(AdminIcon $icon): self
    {
        $action = clone $this;
        $action->icon = $icon;

        return $action;
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
