<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\ViewModels;

/**
 * Nese presentation akci pro zmenu configured stavu feature
 */
final readonly class ModuleFeatureActionViewModel
{
    public function __construct(
        private string $url,
        private bool $enabled,
        private string $label,
    ) {}

    public function url(): string
    {
        return $this->url;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function label(): string
    {
        return $this->label;
    }
}
