<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Popisuje hlavni akci zobrazenou nad datovou tabulkou
 */
final readonly class DataGridPrimaryAction
{
    /**
     * Nastavuje text, ikonu a linkovou, modalni nebo standardni action variantu akce
     */
    public function __construct(
        private string $translationKey,
        private AdminIcon $icon,
        private ?string $href = null,
        private ?string $modalUrl = null,
        private ?string $modalSize = null,
        private ?AdminActionFormDefinition $actionForm = null,
    ) {
        if ($actionForm !== null) {
            if ($href !== null || $modalUrl !== null || $modalSize !== null) {
                throw new InvalidArgumentException('Action primary action cannot define a link or modal target.');
            }

            if ($actionForm->actionUrl() === null || $actionForm->actionKey() === null) {
                throw new InvalidArgumentException('Action primary action requires an AJAX action form.');
            }

            return;
        }

        if ($href === null || trim($href) === '') {
            throw new InvalidArgumentException('Link primary action requires a target URL.');
        }
    }

    /**
     * Vytvori hlavni akci nad existujicim standardnim editor action formularem
     */
    public static function action(string $translationKey, AdminIcon $icon, AdminActionFormDefinition $form): self
    {
        return new self(
            translationKey: $translationKey,
            icon: $icon,
            actionForm: $form,
        );
    }

    /**
     * Vraci prekladovy klic popisku akce
     */
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Vraci ikonu akce
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vraci cilovy odkaz akce
     */
    public function href(): ?string
    {
        return $this->href;
    }

    /**
     * Vraci URL modalniho editoru, pokud jej akce otevre
     */
    public function modalUrl(): ?string
    {
        return $this->modalUrl;
    }

    /**
     * Vraci velikost modalniho editoru, pokud je urcena
     */
    public function modalSize(): ?string
    {
        return $this->modalSize;
    }

    /**
     * Vraci standardni editor action formular, pokud akce neni odkazem
     */
    public function actionForm(): ?AdminActionFormDefinition
    {
        return $this->actionForm;
    }
}
