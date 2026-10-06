<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Cms;

use Lemonade\Admin\Cms\AdminCmsServiceProvider;
use Lemonade\Admin\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Admin\Cms\Routing\CmsRouteRepositoryInterface;
use Lemonade\Admin\Cms\Routing\CmsRouteReservationService;
use Lemonade\Admin\Cms\Routing\PublicCmsRouteRegistrar;
use Lemonade\Admin\Cms\Routing\PublicCmsRouteResolver;
use Lemonade\Admin\Cms\Routing\PublicLocaleResolver;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class AdminCmsServiceProviderTest extends TestCase
{
    public function testItRegistersReusableCmsRoutingWithoutFrontendBindings(): void
    {
        $container = new Container();

        (new AdminCmsServiceProvider())->register($container);

        self::assertTrue($container->isBound(CmsRouteRepositoryInterface::class));
        self::assertTrue($container->isBound(CmsRouteReservationService::class));
        self::assertTrue($container->isBound(PublicLocaleResolver::class));
        self::assertTrue($container->isBound(PublicCmsRouteResolver::class));
        self::assertTrue($container->isBound(PublicCmsRouteController::class));
        self::assertTrue($container->isBound(PublicCmsRouteRegistrar::class));
    }
}
