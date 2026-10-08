<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Cms;

use Lemonade\Admin\Cms\AdminCmsServiceProvider;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Module\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
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
