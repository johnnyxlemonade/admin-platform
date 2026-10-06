<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Nastavuje obsah a zobrazeni dashboardoveho widgetu
 */
final readonly class DashboardWidgetPresentation
{
    /**
     * Vytvori zobrazeni widgetu s preklady, sablonou a volitelnou ikonou
     */
    public function __construct(
        private string $translationGroup,
        private string $titleKey,
        private string|null $descriptionKey,
        private AdminIcon|null $icon,
        private string $contentView,
        private string|null $emptyMessageKey = null,
        private DashboardWidgetSkeleton $skeleton = DashboardWidgetSkeleton::List,
    ) {
        if ($this->translationGroup === '' || $this->titleKey === '' || $this->contentView === '') {
            throw new InvalidArgumentException('Dashboard widget presentation fields must not be empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $this->translationGroup) !== 1) {
            throw new InvalidArgumentException('Dashboard widget translation group must be normalized.');
        }

        if ($this->descriptionKey === '' || $this->emptyMessageKey === '') {
            throw new InvalidArgumentException('Optional dashboard widget translation keys must not be empty strings.');
        }
    }

    /**
     * Vrati skupinu klientskych prekladu widgetu
     */
    public function translationGroup(): string
    {
        return $this->translationGroup;
    }

    /**
     * Vrati prekladovy klic nadpisu widgetu
     */
    public function titleKey(): string
    {
        return $this->titleKey;
    }

    /**
     * Vrati volitelny prekladovy klic popisu widgetu
     */
    public function descriptionKey(): string|null
    {
        return $this->descriptionKey;
    }

    /**
     * Vrati volitelnou ikonu widgetu
     */
    public function icon(): AdminIcon|null
    {
        return $this->icon;
    }

    /**
     * Vrati sablonu obsahu widgetu
     */
    public function contentView(): string
    {
        return $this->contentView;
    }

    /**
     * Vrati volitelny prekladovy klic prazdneho stavu
     */
    public function emptyMessageKey(): string|null
    {
        return $this->emptyMessageKey;
    }

    /**
     * Vrati loading skeleton widgetu
     */
    public function skeleton(): DashboardWidgetSkeleton
    {
        return $this->skeleton;
    }
}
