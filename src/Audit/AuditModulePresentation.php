<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use InvalidArgumentException;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Nastavuje lokalizovane zobrazeni modulu v auditu
 */
final readonly class AuditModulePresentation
{
    /**
     * Vytvori zobrazeni modulu s lokalizovanym nazvem a volitelnou ikonou
     */
    public function __construct(
        private string $moduleCode,
        private string $translationGroup,
        private string $nameKey,
        private AdminIcon|null $icon = null,
    ) {
        if ($this->moduleCode === '' || $this->translationGroup === '' || $this->nameKey === '') {
            throw new InvalidArgumentException('Audit module presentation identity and localization fields must not be empty.');
        }
    }

    /**
     * Vrati kod modulu
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vrati skupinu lokalizace modulu
     */
    public function translationGroup(): string
    {
        return $this->translationGroup;
    }

    /**
     * Vrati prekladovy klic nazvu modulu
     */
    public function nameKey(): string
    {
        return $this->nameKey;
    }

    /**
     * Vrati volitelnou ikonu modulu
     */
    public function icon(): AdminIcon|null
    {
        return $this->icon;
    }
}
