<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje localni actor policy a vazby externich identit na uzivatele
 */
final readonly class CoreIdentityServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje sluzby pro localni a externi application identity
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->scoped(LocalUserDirectory::class, LocalUserDirectory::class);
    }
}
