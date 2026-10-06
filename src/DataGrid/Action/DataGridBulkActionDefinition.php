<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

use InvalidArgumentException;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Nastavuje jednu akci pro hromadnou praci s vybranymi zaznamy
 */
final readonly class DataGridBulkActionDefinition
{
    /**
     * Vytvori nastaveni hromadne akce a overi povinne hodnoty
     *
     * @param list<string> $allowedViews
     */
    public function __construct(
        private string $label,
        private AdminIcon $icon,
        private string $endpoint,
        private string $action,
        private array $allowedViews,
        private ?ConfirmationDefinition $confirmation = null,
        private DataGridRowActionRisk $risk = DataGridRowActionRisk::Normal,
        private bool $download = false,
    ) {
        if ($this->label === '' || $this->endpoint === '' || $this->action === '') {
            throw new InvalidArgumentException('DataGrid bulk action fields must not be empty.');
        }

        if ($this->allowedViews === [] || array_filter($this->allowedViews, static fn(mixed $view): bool => !is_string($view) || $view === '') !== []) {
            throw new InvalidArgumentException('DataGrid bulk actions must declare non-empty allowed views.');
        }
    }

    /**
     * Vrati text zobrazeny uzivateli
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Vrati ikonu akce
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vrati adresu pro odeslani akce
     */
    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Vrati nazev akce odesilany na server
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Vrati pohledy ve kterych je akce dostupna
     *
     * @return list<string>
     */
    public function allowedViews(): array
    {
        return $this->allowedViews;
    }

    /**
     * Vrati potvrzeni zobrazene pred spustenim akce
     */
    public function confirmation(): ?ConfirmationDefinition
    {
        return $this->confirmation;
    }

    /**
     * Vrati uroven rizika akce
     */
    public function risk(): DataGridRowActionRisk
    {
        return $this->risk;
    }

    /**
     * Urci, zda akce odesila standardni formular pro download souboru
     */
    public function download(): bool
    {
        return $this->download;
    }
}
