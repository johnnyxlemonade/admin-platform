<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\ViewModels;

/**
 * Nese lokalizovanou feature projection vybraneho modulu pro management sablonu
 */
final readonly class ModuleFeaturesPageViewModel
{
    /**
     * Nastavuje hodnoty stranky a deklarovane feature v presentation poradi
     *
     * @param list<ModuleFeatureViewModel> $features
     */
    public function __construct(
        private string $moduleName,
        private string $moduleCode,
        private string $moduleKindLabel,
        private string $lifecycleStateLabel,
        private ?string $notice,
        private array $features,
    ) {}

    public function moduleName(): string
    {
        return $this->moduleName;
    }

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    public function moduleKindLabel(): string
    {
        return $this->moduleKindLabel;
    }

    public function lifecycleStateLabel(): string
    {
        return $this->lifecycleStateLabel;
    }

    public function notice(): ?string
    {
        return $this->notice;
    }

    /**
     * Zpristupnuje deklarovane feature pro management sablonu
     *
     * @return list<ModuleFeatureViewModel>
     */
    public function features(): array
    {
        return $this->features;
    }
}
