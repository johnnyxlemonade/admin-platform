<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\DataGrid\DataGridPrimaryAction;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

/**
 * Overuje primary akci nad existujicim editor action formularem
 */
final class DataGridPrimaryActionTest extends TestCase
{
    /**
     * Primary action drzi jeden canonical formular bez duplikace transportnich hodnot
     */
    public function testActionUsesExistingEditorFormDefinition(): void
    {
        $form = new AdminEditorFormDefinition(
            id: 'news-create-draft-form',
            action: '/admin/content/news/create',
            actionUrl: '/admin/content/news/ajax',
            actionKey: 'create',
            navigateToEditAfterCreate: true,
        );

        $action = DataGridPrimaryAction::action('news.actions.create', AdminIcon::PlusLg, $form);

        self::assertSame($form, $action->actionForm());
        self::assertNull($action->href());
        self::assertNull($action->modalUrl());
    }
}
