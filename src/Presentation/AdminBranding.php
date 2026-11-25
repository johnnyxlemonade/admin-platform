<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use InvalidArgumentException;

/**
 * Describes host-provided values displayed by the administration
 */
final readonly class AdminBranding
{
    /**
     * Creates the host-controlled administration branding
     */
    public function __construct(
        private string $applicationName = 'Administration',
        private string $homeUrl = '/',
        private ?string $oidcProviderDisplayName = null,
        private ?string $themeColor = null,
        private ?string $applicationVersion = '1.0',
    ) {
        if (trim($this->applicationName) === '') {
            throw new InvalidArgumentException('Admin branding application name must not be empty.');
        }

        if (trim($this->homeUrl) === '') {
            throw new InvalidArgumentException('Admin branding home URL must not be empty.');
        }

        if ($this->applicationVersion !== null && trim($this->applicationVersion) === '') {
            throw new InvalidArgumentException('Admin branding application version must not be empty.');
        }
    }

    /**
     * Returns the application name displayed in the administration
     */
    public function applicationName(): string
    {
        return $this->applicationName;
    }

    /**
     * Returns the application version displayed in the administration
     */
    public function getApplicationVersion(): ?string
    {
        return $this->applicationVersion;
    }

    /**
     * Returns the host application home URL
     */
    public function homeUrl(): string
    {
        return $this->homeUrl;
    }

    /**
     * Returns the displayed OIDC provider name
     */
    public function oidcProviderDisplayName(): ?string
    {
        return $this->oidcProviderDisplayName;
    }

    /**
     * Returns the browser theme color
     */
    public function themeColor(): ?string
    {
        return $this->themeColor;
    }
}
