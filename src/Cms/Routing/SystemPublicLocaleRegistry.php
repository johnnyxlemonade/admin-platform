<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Cms\Routing\PublicLocaleRegistryInterface;

/**
 * Zpristupnuje systemovy katalog jazyku verejnemu CMS routovani
 */
final class SystemPublicLocaleRegistry implements PublicLocaleRegistryInterface
{
    /**
     * Nastavuje canonical registry jazyku Admin platformy
     */
    public function __construct(private readonly LanguageRegistry $languages) {}

    /**
     * Vrati vychozi lokalizaci pro verejne URL
     */
    public function defaultLocale(): string
    {
        return $this->languages->defaultLocale();
    }

    /**
     * Overi aktivni nevychozi lokalizaci pro locale prefix
     */
    public function isEnabledNonDefault(string $locale): bool
    {
        return $this->languages->isEnabledNonDefault($locale);
    }

    /**
     * Overi, zda kod odpovida znamemu systemovemu jazyku
     */
    public function isKnownLocale(string $locale): bool
    {
        return $this->languages->isKnownLocale($locale);
    }
}
