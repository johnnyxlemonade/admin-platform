<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Provides the fixed partner identity owned by Admin Platform
 */
enum AdminPartner
{
    case Dcuk;

    /**
     * Returns the partner website URL
     */
    public function getUrl(): string
    {
        return match ($this) {
            self::Dcuk => 'https://dcuk.cz/?utm_source=admin-platform&utm_medium=referral&utm_campaign=dcuk-branding&utm_content=auth-partner-logo',
        };
    }

    /**
     * Returns the partner logo URL
     */
    public function getLogoUrl(): string
    {
        return match ($this) {
            self::Dcuk => '/assets/admin/images/branding/logo-dcuk.svg',
        };
    }

    /**
     * Returns the accessible partner name
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Dcuk => 'Datové centrum Ústeckého kraje',
        };
    }
}
