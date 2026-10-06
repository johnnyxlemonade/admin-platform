<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetDefinitionTest extends TestCase
{
    public function testDefinitionRequiresAWidgetCodeWithinItsModuleNamespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with its module code');

        new DashboardWidgetDefinition(
            code: 'system.audit.recent',
            moduleCode: 'system.users',
            presentation: $this->presentation(),
            layout: $this->layout(),
            access: DashboardWidgetAccess::permission('system.users.view'),
        );
    }

    public function testDefinitionRequiresNonEmptyIdentityFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identity fields must not be empty');

        new DashboardWidgetDefinition(
            code: '',
            moduleCode: 'system.users',
            presentation: $this->presentation(),
            layout: $this->layout(),
            access: DashboardWidgetAccess::permission('system.users.view'),
        );
    }

    public function testDefinitionRequiresANonEmptyModuleCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identity fields must not be empty');

        new DashboardWidgetDefinition(
            code: 'system.users.recent',
            moduleCode: '',
            presentation: $this->presentation(),
            layout: $this->layout(),
            access: DashboardWidgetAccess::permission('system.users.view'),
        );
    }

    public function testDefinitionExposesItsTypedConfiguration(): void
    {
        $presentation = $this->presentation();
        $layout = $this->layout();
        $access = DashboardWidgetAccess::permission('system.users.view');
        $definition = new DashboardWidgetDefinition(
            code: 'system.users.recent',
            moduleCode: 'system.users',
            presentation: $presentation,
            layout: $layout,
            access: $access,
        );

        self::assertSame($presentation, $definition->presentation());
        self::assertSame($layout, $definition->layout());
        self::assertSame($access, $definition->access());
    }

    private function presentation(): DashboardWidgetPresentation
    {
        return new DashboardWidgetPresentation(
            translationGroup: 'users',
            titleKey: 'users.widgets.recent.title',
            descriptionKey: null,
            icon: AdminIcon::People,
            contentView: 'users::widgets/recent',
        );
    }

    private function layout(): DashboardWidgetLayout
    {
        return new DashboardWidgetLayout(
            defaultOrder: 100,
            defaultSize: DashboardWidgetSize::Medium,
            supportedSizes: [DashboardWidgetSize::Medium],
        );
    }
}
