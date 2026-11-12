<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Image\ImageUrlGenerator;

/**
 * Vytvari jednotnou Admin presentation pro registrovane image varianty a fallbacky
 */
final readonly class AdminThumbnailComponent
{
    /**
     * Nastavuje generator canonical URL registrovanych image variant
     */
    public function __construct(private ImageUrlGenerator $images) {}

    /**
     * Vytvori thumbnail s URL podle globalniho ID souboru
     */
    public function image(
        int $fileId,
        string $alt = '',
        ?string $fallback = null,
        string $size = 'compact',
        string $shape = 'rounded',
        string $presentation = 'thumbnail',
    ): AdminThumbnail {
        return new AdminThumbnail(
            url: $this->images->url($this->variant($presentation), $fileId),
            alt: $alt,
            fallback: $fallback,
            size: $size,
            shape: $shape,
        );
    }

    /**
     * Prevadi sdileny presentation mode na jedinou povolenou shared image variantu
     */
    private function variant(string $presentation): string
    {
        return match ($presentation) {
            'datagrid' => 'admin-datagrid',
            'thumbnail' => 'admin-thumb',
            'preview' => 'admin-preview',
            default => throw new \InvalidArgumentException('Admin thumbnail presentation is invalid.'),
        };
    }

    /**
     * Vytvori thumbnail bez image requestu, ktery zobrazi pouze presentation fallback
     */
    public function fallback(
        ?string $fallback = null,
        string $size = 'compact',
        string $shape = 'rounded',
    ): AdminThumbnail {
        return new AdminThumbnail(
            url: null,
            alt: '',
            fallback: $fallback,
            size: $size,
            shape: $shape,
        );
    }
}
