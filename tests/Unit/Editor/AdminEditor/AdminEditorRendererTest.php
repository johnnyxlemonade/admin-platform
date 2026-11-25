<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBlock;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Layout\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\Layout\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderer;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewResourceRegistry;
use PHPUnit\Framework\TestCase;

final class AdminEditorRendererTest extends TestCase
{
    public function testFieldColumnRejectsInvalidBootstrapSpan(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FieldColumn(
            field: Field::text('name', 'Name'),
            md: 13,
        );
    }

    /**
     * Overi explicitni selhani pro neznamy block kontrakt
     */
    public function testUnknownBlockIsNotSilentlyIgnored(): void
    {
        $block = new readonly class implements AdminEditorBlock {};
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->block($block)
            ->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported AdminEditor block');

        (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());
    }

    public function testCustomViewCanReuseTheActiveFieldRenderer(): void
    {
        $resources = new ViewResourceRegistry();
        $resources->register('fixtures', dirname(__DIR__, 3) . '/Fixtures/Editor');
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->block(new CustomViewBlock('fixtures::custom-field-renderer'))
            ->build();

        $html = (new AdminEditorRenderer(new View(resources: $resources)))
            ->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString(AdminEditorFieldRenderer::class, $html);
    }

    public function testTabbedEditorEnablesResponsiveOverflowNavigation(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->tab(new AdminEditorTab('content', 'Content', blocks: [], default: true))
            ->tab(new AdminEditorTab('settings', 'Settings', blocks: []))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('data-lemonade-tabs data-lemonade-tabs-overflow', $html);
    }
}
