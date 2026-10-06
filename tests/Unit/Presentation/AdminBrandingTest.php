<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use InvalidArgumentException;
use Lemonade\Admin\Presentation\AdminBranding;
use PHPUnit\Framework\TestCase;

final class AdminBrandingTest extends TestCase
{
    public function testItCarriesHostPresentationForAnotherApplication(): void
    {
        $branding = new AdminBranding(
            applicationName: 'Example Console',
            homeUrl: 'https://example.test/',
            partnerUrl: 'https://partner.example.test/',
            partnerLogoUrl: 'https://partner.example.test/logo.svg',
            partnerLabel: 'Example Partner',
            oidcProviderDisplayName: 'Example Identity',
            themeColor: '#d6f458',
        );

        self::assertSame('Example Console', $branding->applicationName);
        self::assertSame('https://example.test/', $branding->homeUrl);
        self::assertSame('Example Identity', $branding->oidcProviderDisplayName);
        self::assertSame('#d6f458', $branding->themeColor);
        self::assertTrue($branding->hasPartner());
    }

    public function testItAllowsAnAdminWithoutHostHomeOrPartnerPresentation(): void
    {
        $branding = new AdminBranding('Example Console');

        self::assertNull($branding->homeUrl);
        self::assertNull($branding->oidcProviderDisplayName);
        self::assertNull($branding->themeColor);
        self::assertFalse($branding->hasPartner());
    }

    public function testItRequiresACompletePartnerPresentation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminBranding(
            applicationName: 'Example Console',
            partnerUrl: 'https://partner.example.test/',
        );
    }
}
