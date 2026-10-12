<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderer;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use PHPUnit\Framework\TestCase;

final class AdminEditorRendererTest extends TestCase
{
    public function testItRendersFormLevelValidationError(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->section(new SectionBlock('details', [AdminEditorFieldDefinition::text('name', 'Name')], 'Details'))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext(errors: ['_form' => 'Unable to save']));

        self::assertStringContainsString('Unable to save', $html);
    }

    public function testUntitledSectionDoesNotReferenceMissingHeading(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->section(new SectionBlock('details', [AdminEditorFieldDefinition::text('name', 'Name')]))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringNotContainsString('aria-labelledby="example-editor-details"', $html);
    }

    public function testTabsUseUniqueIdsAndSemanticControlRelationships(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->tab(new AdminEditorTab('details', 'Details', [
                new SectionBlock('details', [AdminEditorFieldDefinition::text('name', 'Name')], 'Details'),
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
            field: AdminEditorFieldDefinition::text('name', 'Name'),
            md: 13,
        );
    }
}
