<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms;

use Lemonade\Admin\Cms\Routing\ComposerPublicModuleStateResolver;
use Lemonade\Admin\Cms\Routing\SystemPublicLocaleRegistry;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Propojuje Composer dostupnost modulu a locale capability s CMS runtime porty
 */
final readonly class AdminCmsServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje Admin implementace CMS runtime portu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->transient(PublicLocaleRegistryInterface::class, SystemPublicLocaleRegistry::class);
        $container->transient(PublicModuleStateResolverInterface::class, ComposerPublicModuleStateResolver::class);
    }
}
