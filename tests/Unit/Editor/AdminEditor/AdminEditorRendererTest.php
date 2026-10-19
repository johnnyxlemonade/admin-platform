<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBlock;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Layout\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderer;
use PHPUnit\Framework\TestCase;

final class AdminEditorRendererTest extends TestCase
{
    public function testItRendersFormLevelValidationError(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->section(new SectionBlock('details', [Field::text('name', 'Name')], 'Details'))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext(errors: ['_form' => 'Unable to save']));

        self::assertStringContainsString('Unable to save', $html);
    }

    public function testUntitledSectionDoesNotReferenceMissingHeading(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->section(new SectionBlock('details', [Field::text('name', 'Name')]))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringNotContainsString('aria-labelledby="example-editor-details"', $html);
    }

    public function testTabsUseUniqueIdsAndSemanticControlRelationships(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->tab(new AdminEditorTab('details', 'Details', [
                new SectionBlock('details', [Field::text('name', 'Name')], 'Details'),
            ], default: true))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('id="example-editor-tab-button-details"', $html);
        self::assertStringContainsString('id="example-editor-tab-details"', $html);
        self::assertStringContainsString('aria-controls="example-editor-tab-details"', $html);
        self::assertStringContainsString('aria-labelledby="example-editor-tab-button-details"', $html);
    }

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
}
