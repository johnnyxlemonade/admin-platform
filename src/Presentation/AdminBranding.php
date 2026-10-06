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
        public string $applicationName = 'Administration',
        public ?string $homeUrl = null,
        public ?string $partnerUrl = null,
        public ?string $partnerLogoUrl = null,
        public ?string $partnerLabel = null,
        public ?string $oidcProviderDisplayName = null,
        public ?string $themeColor = null,
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
     * Rozhoduje, zda ma layout zobrazit odkaz na partnera hosta
     */
    public function hasPartner(): bool
    {
        return $this->partnerUrl !== null;
    }
}
