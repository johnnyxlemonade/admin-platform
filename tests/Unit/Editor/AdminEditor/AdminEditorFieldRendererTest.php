<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorStatusVariant;
use PHPUnit\Framework\TestCase;

final class AdminEditorFieldRendererTest extends TestCase
{
    public function testItRendersLabelHelpErrorAndRequiredState(): void
    {
        $field = AdminEditorFieldDefinition::text('title', 'Title')->required()->help('Enter a title');
        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(errors: ['title' => 'Title is required']), 'article-editor');

        self::assertStringContainsString('<label', $html);
        self::assertStringContainsString('Enter a title', $html);
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

    public function testReadonlyEditableRendersInputUnlockActionAndMarker(): void
    {
        $field = AdminEditorFieldDefinition::readonlyEditable('slug', 'URL address')
            ->value('article-url')
            ->unlock('Edit URL')
            ->marker(AdminEditorFieldDefinition::hidden('slug_manually_edited')->defaultValue('0'));

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'news-editor');

        self::assertStringContainsString('readonly', $html);
        self::assertStringContainsString('data-lemonade-editable-field-unlock', $html);
        self::assertStringContainsString('data-lemonade-editable-field-marker="news-editor-slug_manually_edited"', $html);
        self::assertStringContainsString('name="slug_manually_edited"', $html);
    }

    public function testReadonlyInputRendersExplicitAutocompleteWithoutHtmlRequiredOrDisabled(): void
    {
        $field = AdminEditorFieldDefinition::email('email', 'Email')
            ->required()
            ->readonly()
            ->autocomplete('off');

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString('class="lm-form-label required"', $html);
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
        self::assertStringContainsString('name="status" type="hidden" value="draft"', $html);
    }

    public function testReadonlyStatusRendersSemanticStatusControl(): void
    {
        $field = AdminEditorFieldDefinition::statusDisplay(
            name: 'status',
            label: 'Status',
            variant: AdminEditorStatusVariant::Success,
        )
            ->value('Active');

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'language-editor');

        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('Active', $html);
        self::assertStringNotContainsString('<input', $html);
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

    public function testSelectOptionCanExposeItsTranslationKey(): void
    {
        $field = AdminEditorFieldDefinition::select('type', 'Type')
            ->options(['info' => 'Information'])
            ->optionLabelKeys(['info' => 'notifications.types.info']);

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(values: ['type' => 'info']), 'notifications-editor');

        self::assertStringContainsString('<option value="info" selected data-lemonade-i18n="notifications.types.info">Information</option>', $html);
    }

    public function testSelectRendersNumericOptionValues(): void
    {
        $field = AdminEditorFieldDefinition::select('role', 'Role')
            ->options([2 => 'Editor'])
            ->value(2);

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString('<option value="2" selected>Editor</option>', $html);
    }

    public function testNumberFieldRendersNumericControl(): void
    {
        $field = AdminEditorFieldDefinition::number('sort_order', 'Order')->value(5)->attributes(['step' => '1']);

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'language-editor');

        self::assertStringContainsString('type="number"', $html);
        self::assertStringContainsString('step="1"', $html);
    }

    public function testEmailAndTelephoneFieldsKeepTheirNativeInputTypes(): void
    {
        $renderer = new AdminEditorFieldRenderer();

        $email = $renderer->render(AdminEditorFieldDefinition::email('email', 'Email'), new AdminEditorRenderContext(), 'user-editor');
        $phone = $renderer->render(AdminEditorFieldDefinition::tel('phone', 'Phone'), new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString('type="email"', $email);
        self::assertStringContainsString('type="tel"', $phone);
    }

    public function testHiddenFieldRendersDeclaredTransportAttributes(): void
    {
        $field = AdminEditorFieldDefinition::hidden('version')
            ->value(3)
            ->attributes(['data-lemonade-editor-version' => true]);

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString('data-lemonade-editor-version', $html);
    }
}
