<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use InvalidArgumentException;

/**
 * Popisuje hostem dodane hodnoty vykreslovane administraci
 */
final readonly class AdminBranding
{
    /**
     * Nastavuje nazev aplikace, odkazy a browser barvu hosta v administraci
     */
    public function __construct(
        private string $applicationName = 'Administration',
        private string $homeUrl = '/',
        private ?string $partnerUrl = null,
        private ?string $partnerLogoUrl = null,
        private ?string $partnerLabel = null,
        private ?string $oidcProviderDisplayName = null,
        private ?string $themeColor = null,
    ) {
        if (trim($this->applicationName) === '') {
            throw new InvalidArgumentException('Admin branding application name must not be empty.');
        }

        $partnerValues = [$this->partnerUrl, $this->partnerLogoUrl, $this->partnerLabel];
        $configuredPartnerValues = count(array_filter(
            $partnerValues,
            static fn(?string $value): bool => $value !== null,
        ));
        if ($configuredPartnerValues !== 0 && $configuredPartnerValues !== count($partnerValues)) {
            throw new InvalidArgumentException('Admin branding partner presentation requires URL, logo URL and label.');
        }
    }

    /**
     * Vrati nazev aplikace zobrazovany v administraci
     */
    public function applicationName(): string
    {
        return $this->applicationName;
    }

    /**
     * Vrati odkaz na hlavni web hosta
     */
    public function homeUrl(): string
    {
        return $this->homeUrl;
    }

    /**
     * Vrati adresu partnerskeho webu
     */
    public function partnerUrl(): ?string
    {
        return $this->partnerUrl;
    }

    /**
     * Vrati adresu loga partnera
     */
    public function partnerLogoUrl(): ?string
    {
        return $this->partnerLogoUrl;
    }

    /**
     * Vrati nazev partnera
     */
    public function partnerLabel(): ?string
    {
        return $this->partnerLabel;
    }

    /**
     * Vrati zobrazovany nazev OIDC providera
     */
    public function oidcProviderDisplayName(): ?string
    {
        return $this->oidcProviderDisplayName;
    }

    /**
     * Vrati barvu hlavicky prohlizece
     */
    public function themeColor(): ?string
    {
        return $this->themeColor;
    }

    /**
     * Rozhoduje, zda ma layout zobrazit odkaz na partnera hosta
     */
    public function hasPartner(): bool
    {
        return $this->partnerUrl !== null;
    }
}
