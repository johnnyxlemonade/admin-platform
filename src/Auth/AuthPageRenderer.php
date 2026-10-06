<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Renderuje stranky prihlaseni ve sdilenem admin rozlozeni
 */
final class AuthPageRenderer
{
    /**
     * Nastavi renderer sablon prihlaseni
     */
    public function __construct(
        private readonly ViewRendererInterface $views,
        private readonly AdminBranding $branding,
        private readonly AdminAssetManifest $assets,
    ) {}

    /**
     * Vykresli stranku prihlaseni s jejim obsahem a HTTP stavem
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        $pageData = [...$data, 'branding' => $this->branding, 'adminAssets' => $this->assets];

        return $this->views->render('admin-auth::layouts.auth', [
            ...$pageData,
            'content' => $this->views->content($view, $pageData),
        ], $status);
    }
}
