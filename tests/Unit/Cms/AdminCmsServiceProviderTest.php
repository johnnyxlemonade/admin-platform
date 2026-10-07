<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Cms;

use Lemonade\Admin\Cms\AdminCmsServiceProvider;
use Lemonade\Cms\Routing\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Cms\Routing\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class AdminCmsServiceProviderTest extends TestCase
{
    public function testItBindsAdminAdaptersToCmsRuntimePorts(): void
    {
        $container = new Container();

        (new AdminCmsServiceProvider())->register($container);

        self::assertTrue($container->isBound(PublicLocaleRegistryInterface::class));
        self::assertTrue($container->isBound(PublicModuleStateResolverInterface::class));
        self::assertTrue($container->isBound(PublicModuleRoutePrefixRepositoryInterface::class));
    }
}
