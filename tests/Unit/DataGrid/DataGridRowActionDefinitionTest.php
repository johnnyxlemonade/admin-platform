<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionRisk;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

final class DataGridRowActionDefinitionTest extends TestCase
{
    public function testNavigationActionWithoutConfirmationIsValid(): void
    {
        $action = new DataGridRowActionDefinition('edit', 'Edit', '/admin/users/edit/15', 'GET');

        self::assertSame([
            'key' => 'edit',
            'label' => 'Edit',
            'url' => '/admin/users/edit/15',
            'method' => 'GET',
            'confirm' => null,
        ], $action->toArray());
    }

    public function testAjaxActionSerializesItsTypedConfirmationMetadata(): void
    {
        $action = new DataGridRowActionDefinition(
            'activate',
            'Activate',
            '/admin/users/ajax/15',
            'POST',
            new ConfirmationDefinition('users.confirm.activate', 'users.confirm.activate_title'),
        );

        $serialized = $action->toArray();

        self::assertSame('users.confirm.activate', $serialized['confirm']);
        self::assertSame('users.confirm.activate_title', $serialized['confirmTitle'] ?? null);
    }

    public function testAjaxActionWithoutConfirmationIsValid(): void
    {
        $action = new DataGridRowActionDefinition('refresh', 'Refresh', '/admin/example/ajax/15', 'POST');

        self::assertSame('POST', $action->toArray()['method']);
        self::assertNull($action->toArray()['confirm']);
    }

    public function testNavigationActionCanExplicitlyOpenAModal(): void
    {
        $action = new DataGridRowActionDefinition(
            'edit',
            'Edit',
            '/admin/languages/edit/15',
            'GET',
            null,
            '/admin/languages/edit/15/modal',
        );

        self::assertSame('/admin/languages/edit/15/modal', $action->toArray()['modalUrl'] ?? null);
    }

    public function testNavigationActionCanPassAnExplicitModalSize(): void
    {
        $action = new DataGridRowActionDefinition(
            'edit',
            'Edit',
            '#',
            'GET',
            null,
            '/admin/languages/edit/15/modal',
            'medium',
        );

        self::assertSame('medium', $action->toArray()['modalSize'] ?? null);
    }

    public function testActionSerializesExplicitPresentationMetadata(): void
    {
        $action = new DataGridRowActionDefinition(
            key: 'edit',
            label: 'Edit',
            url: '#',
            method: 'GET',
            modalUrl: '/admin/languages/edit/15/modal',
            modalSize: 'medium',
            kind: DataGridRowActionKind::Modal,
            placement: DataGridRowActionPlacement::Primary,
            risk: DataGridRowActionRisk::Normal,
            refresh: true,
        );

        self::assertSame([
            'modalUrl' => '/admin/languages/edit/15/modal',
            'modalSize' => 'medium',
            'kind' => 'modal',
            'placement' => 'primary',
            'risk' => 'normal',
            'refresh' => true,
        ], array_intersect_key($action->toArray(), array_flip([
            'kind',
            'placement',
            'risk',
            'refresh',
            'modalUrl',
            'modalSize',
        ])));
    }

    public function testActionCanUseInlinePresentation(): void
    {
        $action = new DataGridRowActionDefinition(
            key: 'enable',
            label: 'Enable language English',
            url: '/admin/languages/ajax/15',
            method: 'POST',
            kind: DataGridRowActionKind::Mutation,
            placement: DataGridRowActionPlacement::Inline,
            refresh: true,
        );

        $serialized = $action->toArray();

        self::assertSame('inline', $serialized['placement'] ?? null);
    }

    public function testActionCanSerializeAnExplicitAccessibleLabel(): void
    {
        $action = (new DataGridRowActionDefinition('edit', 'Edit', '#', 'GET'))
            ->withAriaLabel('Edit language English');

        self::assertSame('Edit', $action->toArray()['label']);
        self::assertSame('Edit language English', $action->toArray()['ariaLabel'] ?? null);
    }

    public function testActionCanSerializeItsCentralIconMetadata(): void
    {
        $action = (new DataGridRowActionDefinition('edit', 'Edit', '#', 'GET'))
            ->withIcon(AdminIcon::PencilSquare);

        self::assertSame('pencil-square', $action->toArray()['icon'] ?? null);
    }
}
