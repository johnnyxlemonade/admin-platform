<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\ViewModels;

/**
 * Nese lokalizovanou feature presentation a volitelnou management action
 */
final readonly class ModuleFeatureViewModel
{
    public function __construct(
        private string $code,
        private string $label,
        private string $categoryLabel,
        private string $configuredStateLabel,
        private string $effectiveStateLabel,
        private bool $required,
        private ?string $readOnlyLabel,
        private ?ModuleFeatureActionViewModel $action,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function categoryLabel(): string
    {
        return $this->categoryLabel;
    }

    public function configuredStateLabel(): string
    {
        return $this->configuredStateLabel;
    }

    public function effectiveStateLabel(): string
    {
        return $this->effectiveStateLabel;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function readOnlyLabel(): ?string
    {
        return $this->readOnlyLabel;
    }

    public function action(): ?ModuleFeatureActionViewModel
    {
        return $this->action;
    }
}
