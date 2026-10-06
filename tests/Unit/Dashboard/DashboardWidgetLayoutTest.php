<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Dashboard\DashboardWidgetLayout;
use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DashboardWidgetLayoutTest extends TestCase
{
    public function testLayoutRequiresDefaultSizeToBeSupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('default size must be supported');

        new DashboardWidgetLayout(100, DashboardWidgetSize::Large, [DashboardWidgetSize::Medium]);
    }

    public function testLayoutRejectsDuplicateSupportedSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supported sizes must be unique');

        new DashboardWidgetLayout(100, DashboardWidgetSize::Medium, [DashboardWidgetSize::Medium, DashboardWidgetSize::Medium]);
    }

    public function testLayoutRejectsNonListSupportedSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supported sizes must be a list');

        (new ReflectionClass(DashboardWidgetLayout::class))->newInstance(
            100,
            DashboardWidgetSize::Medium,
            ['medium' => DashboardWidgetSize::Medium],
        );
    }
}
