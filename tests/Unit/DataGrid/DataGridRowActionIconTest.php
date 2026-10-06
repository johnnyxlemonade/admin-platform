<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\DataGrid\Action\DataGridRowActionIcon;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataGridRowActionIconTest extends TestCase
{
    #[DataProvider('actionIcons')]
    public function testKnownActionKeyResolvesToTheCentralIcon(string $actionKey, AdminIcon $icon): void
    {
        self::assertSame($icon, DataGridRowActionIcon::forKey($actionKey));
    }

    public function testUnknownActionKeyHasNoImplicitIcon(): void
    {
        self::assertNull(DataGridRowActionIcon::forKey('unknown-action'));
    }

    /** @return iterable<string, array{string, AdminIcon}> */
    public static function actionIcons(): iterable
    {
        yield 'edit' => ['edit', AdminIcon::PencilSquare];
        yield 'enable' => ['enable', AdminIcon::CheckCircle];
        yield 'activate' => ['activate', AdminIcon::CheckCircle];
        yield 'disable' => ['disable', AdminIcon::PauseCircle];
        yield 'deactivate' => ['deactivate', AdminIcon::PauseCircle];
        yield 'delete' => ['delete', AdminIcon::Trash3];
        yield 'remove' => ['remove', AdminIcon::Trash3];
        yield 'restore' => ['restore', AdminIcon::ArrowCounterclockwise];
        yield 'set default' => ['set-default', AdminIcon::Star];
        yield 'open' => ['open', AdminIcon::BoxArrowUpRight];
        yield 'detail' => ['detail', AdminIcon::BoxArrowUpRight];
        yield 'view' => ['view', AdminIcon::Eye];
        yield 'features' => ['features', AdminIcon::Sliders];
        yield 'settings' => ['settings', AdminIcon::Gear];
        yield 'install' => ['install', AdminIcon::Boxes];
    }
}
