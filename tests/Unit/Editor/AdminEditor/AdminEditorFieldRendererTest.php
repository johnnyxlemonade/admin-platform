<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use PHPUnit\Framework\TestCase;

final class AdminEditorFieldRendererTest extends TestCase
{
    public function testItRendersLabelHelpErrorAndRequiredState(): void
    {
        $field = AdminEditorFieldDefinition::text('title', 'Title')->required()->help('Enter a title');
        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(errors: ['title' => 'Title is required']), 'article-editor');

        self::assertStringContainsString('Title is required', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
    }

    public function testOldInputHasPriorityOverConfiguredAndContextValue(): void
    {
        $field = AdminEditorFieldDefinition::text('title', 'Title')->value('Configured')->defaultValue('Default');
        $context = new AdminEditorRenderContext(values: ['title' => 'Loaded'], oldInput: ['title' => 'Old input']);

        $html = (new AdminEditorFieldRenderer())->render($field, $context, 'article-editor');

        self::assertStringContainsString('value="Old input"', $html);
        self::assertStringNotContainsString('value="Configured"', $html);
    }

    public function testReadonlyInputRendersExplicitAutocompleteWithoutHtmlRequiredOrDisabled(): void
    {
        $field = AdminEditorFieldDefinition::email('email', 'Email')
            ->required()
            ->readonly()
            ->autocomplete('off');

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString(' readonly', $html);
        self::assertStringContainsString('autocomplete="off"', $html);
        self::assertStringNotContainsString(' disabled', $html);
        self::assertDoesNotMatchRegularExpression('/<input[^>]*\srequired(?:\s|>)/', $html);
    }

    public function testEditableInputKeepsItsExistingRequiredContractWithoutAutocomplete(): void
    {
        $field = AdminEditorFieldDefinition::email('email', 'Email')->required();

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString(' required', $html);
        self::assertStringNotContainsString(' readonly', $html);
        self::assertStringNotContainsString(' disabled', $html);
        self::assertStringNotContainsString('autocomplete=', $html);
    }

    public function testReadonlySelectUsesDisabledControlAndPreservesSubmittedValue(): void
    {
        $field = AdminEditorFieldDefinition::select('status', 'Status')
            ->options(['draft' => 'Draft', 'published' => 'Published'])
            ->value('draft')
            ->readonly();

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'article-editor');

        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString(' disabled', $html);
        self::assertStringContainsString('name="status"', $html);
        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('value="draft"', $html);
    }

    public function testDisabledSelectDoesNotAddPreservedValue(): void
    {
        $field = AdminEditorFieldDefinition::select('status', 'Status')
            ->options(['draft' => 'Draft'])
            ->value('draft')
            ->disabled();

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'article-editor');

        self::assertStringNotContainsString('name="status" type="hidden"', $html);
    }

    public function testMultipleSelectUsesArrayTransportAndSelectedValues(): void
    {
        $field = AdminEditorFieldDefinition::select('tags', 'Tags')
            ->multiple()
            ->options(['Culture' => 'Culture', 'Sport' => 'Sport'])
            ->value(['Sport']);

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'article-editor');

        self::assertStringContainsString('name="tags[]"', $html);
        self::assertStringContainsString(' multiple', $html);
        self::assertStringContainsString('value="Sport"', $html);
        self::assertStringNotContainsString('value="Culture" selected', $html);
    }
}
