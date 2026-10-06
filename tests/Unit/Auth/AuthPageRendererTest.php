<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Assets\AdminAssetConfiguration;
use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\View\ViewRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AuthPageRendererTest extends TestCase
{
    public function testItMakesHostBrandingAvailableToTheAuthLayoutAndPage(): void
    {
        $branding = new AdminBranding(applicationName: 'Example Console');
        $response = $this->createMock(ResponseInterface::class);
        $views = $this->createMock(ViewRendererInterface::class);
        $views->expects(self::once())
            ->method('content')
            ->with(
                'admin-auth::login',
                self::callback(static fn(array $data): bool => $data['branding'] === $branding),
            )
            ->willReturn('<main>Login</main>');
        $views->expects(self::once())
            ->method('render')
            ->with(
                'admin-auth::layouts.auth',
                self::callback(static fn(array $data): bool => $data['branding'] === $branding && $data['content'] === '<main>Login</main>'),
                200,
            )
            ->willReturn($response);

        $assets = new AdminAssetManifest(
            (new ApplicationContextFactory())->create(sys_get_temp_dir(), ['APP_ENV' => 'testing']),
            new AdminAssetConfiguration('/assets/admin/', 'assets/admin'),
        );
        $result = (new AuthPageRenderer($views, $branding, $assets))->render('admin-auth::login');

        self::assertSame($response, $result);
    }
}
