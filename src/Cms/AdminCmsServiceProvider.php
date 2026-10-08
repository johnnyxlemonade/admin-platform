<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms;

use Lemonade\Admin\Cms\Routing\ModuleLifecyclePublicModuleStateResolver;
use Lemonade\Admin\Cms\Routing\ModuleRoutePrefixPublicRepository;
use Lemonade\Admin\Cms\Routing\SystemPublicLocaleRegistry;
use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Module\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Propojuje Admin lifecycle a locale capability s CMS runtime porty
 */
final class AdminCmsServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje Admin implementace CMS runtime portu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(LanguageRegistry::class, LanguageRegistry::class);
        $container->singleton(PublicLocaleRegistryInterface::class, SystemPublicLocaleRegistry::class);
        $container->singleton(PublicModuleStateResolverInterface::class, ModuleLifecyclePublicModuleStateResolver::class);
        $container->singleton(PublicModuleRoutePrefixRepositoryInterface::class, ModuleRoutePrefixPublicRepository::class);
    }
}
