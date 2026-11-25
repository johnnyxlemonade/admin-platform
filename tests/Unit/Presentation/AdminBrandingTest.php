<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use InvalidArgumentException;
use Lemonade\Admin\Presentation\AdminBranding;
use PHPUnit\Framework\TestCase;

/**
 * Verifies host-controlled administration branding
 */
final class AdminBrandingTest extends TestCase
{
    /**
     * Provides every configured host presentation value
     */
    public function testItProvidesConfiguredHostPresentation(): void
    {
        $branding = new AdminBranding(
            applicationName: 'Example Console',
            homeUrl: 'https://example.test/',
            oidcProviderDisplayName: 'Example Identity',
            themeColor: '#d6f458',
            applicationVersion: '2.4.0',
        );

        self::assertSame('Example Console', $branding->applicationName());
        self::assertSame('2.4.0', $branding->getApplicationVersion());
        self::assertSame('https://example.test/', $branding->homeUrl());
        self::assertSame('Example Identity', $branding->oidcProviderDisplayName());
        self::assertSame('#d6f458', $branding->themeColor());
    }

    /**
     * Uses defaults only for optional presentation values
     */
    public function testItDefaultsOptionalPresentationValues(): void
    {
        $branding = new AdminBranding();

        self::assertSame('Administration', $branding->applicationName());
        self::assertSame('/', $branding->homeUrl());
        self::assertSame('1.0', $branding->getApplicationVersion());
        self::assertNull($branding->oidcProviderDisplayName());
        self::assertNull($branding->themeColor());
    }

    /**
     * Omits the application version when the host disables it
     */
    public function testItCanHideTheApplicationVersion(): void
    {
        $branding = new AdminBranding(
            applicationName: 'Example Console',
            homeUrl: '/',
            applicationVersion: null,
        );

        self::assertNull($branding->getApplicationVersion());
    }

    /**
     * Rejects an empty configured application version
     */
    public function testItRejectsAnEmptyApplicationVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminBranding(
            applicationName: 'Example Console',
            homeUrl: '/',
            applicationVersion: ' ',
        );
    }

    /**
     * Rejects an empty configured application name
     */
    public function testItRejectsAnEmptyApplicationName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminBranding(
            applicationName: ' ',
            homeUrl: '/',
        );
    }

    /**
     * Rejects an empty configured host home URL
     */
    public function testItRejectsAnEmptyHomeUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminBranding(
            applicationName: 'Example Console',
            homeUrl: ' ',
        );
    }
}
