<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistration;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistry;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetRegistryTest extends TestCase
{
    public function testRegistryOrdersDefinitionsByDefaultOrderThenWidgetCode(): void
    {
        $provider = $this->provider(
            $this->definition('system.users.zeta', 100),
            $this->definition('system.users.alpha', 100),
            $this->definition('system.users.first', 10),
        );

        $registry = new DashboardWidgetRegistry([$provider]);

        self::assertSame(
            ['system.users.first', 'system.users.alpha', 'system.users.zeta'],
            array_map(static fn(DashboardWidgetRegistration $registration): string => $registration->definition()->code(), $registry->all()),
        );
    }

    public function testRegistryRejectsDuplicateWidgetCodesAcrossProviders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('system.users.total');

        new DashboardWidgetRegistry([
            $this->provider($this->definition('system.users.total', 10)),
            $this->provider($this->definition('system.users.total', 20)),
        ]);
    }

    public function testRegistryKeepsTheOwningProviderForAWidgetDefinition(): void
    {
        $provider = $this->provider($this->definition('system.users.total', 10));
        $registry = new DashboardWidgetRegistry([$provider]);
        $registration = $registry->registration('system.users.total');

        self::assertNotNull($registration);
        self::assertSame($provider, $registration->provider());
    }

    private function definition(string $code, int $defaultOrder): DashboardWidgetDefinition
    {
        return new DashboardWidgetDefinition(
            code: $code,
            moduleCode: 'system.users',
            presentation: new DashboardWidgetPresentation(
                translationGroup: 'users',
                titleKey: 'users.widgets.title',
                descriptionKey: null,
                icon: AdminIcon::People,
                contentView: 'users::widgets/total',
            ),
            layout: new DashboardWidgetLayout(
                defaultOrder: $defaultOrder,
                defaultSize: DashboardWidgetSize::Small,
                supportedSizes: [DashboardWidgetSize::Small, DashboardWidgetSize::Medium],
            ),
            access: DashboardWidgetAccess::permission('system.users.view'),
        );
    }

    private function provider(DashboardWidgetDefinition ...$definitions): DashboardWidgetProviderInterface
    {
        return new class (array_values($definitions)) implements DashboardWidgetProviderInterface {
            /** @param list<DashboardWidgetDefinition> $definitions */
            public function __construct(private readonly array $definitions) {}

            public function definitions(): iterable
            {
                return $this->definitions;
            }

            public function load(
                DashboardWidgetContext $context,
                DashboardWidgetDefinition $definition,
            ): DashboardWidgetContent {
                return DashboardWidgetContent::empty();
            }
        };
    }
}
