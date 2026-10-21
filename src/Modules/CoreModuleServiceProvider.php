<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje Composer katalog modulu aplikace
 */
final readonly class CoreModuleServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje canonical Composer katalog modulu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ModuleCatalog::class, ModuleCatalog::class);
    }
}
