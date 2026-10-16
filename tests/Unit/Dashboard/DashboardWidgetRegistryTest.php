<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistry;
use Lemonade\Admin\Dashboard\DashboardWidgetResolver;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use PHPUnit\Framework\TestCase;

/**
 * Overuje root-safe katalog dashboard provideru
 */
final class DashboardWidgetRegistryTest extends TestCase
{
    /**
     * Overuje registraci service ID bez runtime instance a duplicit
     */
    public function testRegistryKeepsOnlyUniqueProviderServiceIds(): void
    {
        $registry = new DashboardWidgetRegistry();
        $registry->registerProvider('dashboard.users');
        $registry->registerProvider('dashboard.audit');
        $registry->registerProvider('dashboard.users');

        self::assertSame(['dashboard.users', 'dashboard.audit'], $registry->providerServiceIds());
    }

    /**
     * Overuje materializaci widget provideru s authorization stavem aktualniho requestu
     */
    public function testResolverUsesCurrentRequestProviderGraph(): void
    {
        $registry = new DashboardWidgetRegistry();
        $registry->registerProvider(ScopedDashboardProviderStub::class);
        $container = new Container();
        $container->instance(DashboardWidgetRegistry::class, $registry);
        $container->scoped(DashboardWidgetResolver::class, DashboardWidgetResolver::class);
        $container->freeze();

        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);
        $firstProvider = $firstScope->get(DashboardWidgetResolver::class)->registration('test.module.widget')?->provider();
        $secondProvider = $secondScope->get(DashboardWidgetResolver::class)->registration('test.module.widget')?->provider();

        self::assertInstanceOf(ScopedDashboardProviderStub::class, $firstProvider);
        self::assertInstanceOf(ScopedDashboardProviderStub::class, $secondProvider);
        self::assertNotSame($firstProvider, $secondProvider);
        self::assertNotSame($firstProvider->authorization, $secondProvider->authorization);
        self::assertSame([ScopedDashboardProviderStub::class], $registry->providerServiceIds());

        $firstScope->close();
        $secondScope->close();
    }
}

/**
 * Predstavuje requestovy authorization stav dashboardu
 */
final class DashboardAuthorizationMarker {}

/**
 * Poskytuje widget zavisly na authorization stavu aktualniho requestu
 */
final readonly class ScopedDashboardProviderStub implements DashboardWidgetProviderInterface
{
    public function __construct(public DashboardAuthorizationMarker $authorization) {}

    public function definitions(): iterable
    {
        yield new DashboardWidgetDefinition(
            code: 'test.module.widget',
            moduleCode: 'test.module',
            presentation: new DashboardWidgetPresentation('test', 'test.title', null, AdminIcon::People, 'test::widget'),
            layout: new DashboardWidgetLayout(10, DashboardWidgetSize::Small, [DashboardWidgetSize::Small]),
            access: DashboardWidgetAccess::permission('test.view'),
        );
    }

    public function load(DashboardWidgetContext $context, DashboardWidgetDefinition $definition): DashboardWidgetContent
    {
        return DashboardWidgetContent::empty();
    }
}
