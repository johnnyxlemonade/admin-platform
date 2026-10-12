<?php

declare(strict_types=1);

namespace Lemonade\Admin\System;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Pripravuje zavislosti builtin systemovych modulu
 */
final class SystemModuleServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Urcuje registry potrebne pro skupiny systemove navigace
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Nepridava runtime sluzby mimo deklarovane zavislosti modulu
     */
    public function register(ContainerBuilderInterface $container): void {}
}
