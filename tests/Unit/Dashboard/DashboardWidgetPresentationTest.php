<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Dashboard\DashboardWidgetPresentation;
use Lemonade\Admin\Dashboard\DashboardWidgetSkeleton;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetPresentationTest extends TestCase
{
    public function testPresentationDefaultsToListSkeleton(): void
    {
        $presentation = new DashboardWidgetPresentation('users', 'users.widgets.title', null, AdminIcon::People, 'users::widgets.active');

        self::assertSame(DashboardWidgetSkeleton::List, $presentation->skeleton());
    }

    public function testPresentationRequiresTranslationGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DashboardWidgetPresentation('', 'users.widgets.title', null, AdminIcon::People, 'users::widgets.active');
    }

    public function testPresentationRequiresTitleKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DashboardWidgetPresentation('users', '', null, AdminIcon::People, 'users::widgets.active');
    }

    public function testPresentationRequiresContentView(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DashboardWidgetPresentation('users', 'users.widgets.title', null, AdminIcon::People, '');
    }

    public function testPresentationRequiresNormalizedTranslationGroup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('translation group must be normalized');

        new DashboardWidgetPresentation('System Users', 'users.widgets.title', null, AdminIcon::People, 'users::widgets.active');
    }

    public function testPresentationRejectsEmptyOptionalTranslationKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional dashboard widget translation keys');

        new DashboardWidgetPresentation('users', 'users.widgets.title', '', AdminIcon::People, 'users::widgets.active');
    }

    public function testPresentationRejectsAnEmptyEmptyStateTranslationKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional dashboard widget translation keys');

        new DashboardWidgetPresentation('users', 'users.widgets.title', null, AdminIcon::People, 'users::widgets.active', '');
    }
}
