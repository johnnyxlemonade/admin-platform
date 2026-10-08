<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;

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
     * Vrati jeden cached snapshot systemovych jazyku pro public routing
     */
    public function snapshot(): PublicLocaleSnapshot
    {
        return $this->languages->publicLocaleSnapshot();
    }
}
