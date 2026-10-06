<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorActionDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderContextDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderContextItem;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderer;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorSaveBarDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use Lemonade\Admin\Editor\AdminEditor\TabGroupBlock;
use PHPUnit\Framework\TestCase;

final class AdminEditorRendererTest extends TestCase
{
    public function testItRendersFormLevelError(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->section(new SectionBlock('details', [AdminEditorFieldDefinition::text('name', 'Name')], 'Details'))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext(errors: ['_form' => 'Unable to save']));

        self::assertStringContainsString('Unable to save', $html);
        self::assertStringContainsString('<section', $html);
    }

    public function testSubmitActionUsesExistingActionTransportWhenFormContractProvidesIt(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', actionUrl: '/admin/example/ajax', actionKey: 'save', csrf: false))
            ->header(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition('Example', actions: [new AdminEditorActionDefinition('Save', submit: true, primary: true)]))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('data-lemonade-url="/admin/example/ajax"', $html);
        self::assertStringContainsString('data-lemonade-action-key="save"', $html);
    }

    public function testStandardHeaderActionsRenderCanonicalBackAndSaveIcons(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->header(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition(
                'Example',
                actions: [
                    new AdminEditorActionDefinition('', labelKey: 'admin.common.back', href: '/admin/example'),
                    new AdminEditorActionDefinition('', labelKey: 'admin.common.save', submit: true, primary: true),
                ],
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('class="btn btn-light lm-button-with-icon"', $html);
        self::assertStringContainsString('class="btn btn-primary lm-button-with-icon"', $html);
        self::assertStringContainsString('<i class="bi bi-arrow-left" aria-hidden="true"></i><span data-lemonade-i18n="admin.common.back"></span>', $html);
        self::assertStringContainsString('<i class="bi bi-floppy" aria-hidden="true"></i><span data-lemonade-i18n="admin.common.save"></span>', $html);
        self::assertStringNotContainsString('class="btn btn-light lm-button-with-icon" href="/admin/example" data-lemonade-i18n', $html);
    }

    public function testFullPageCreateFormDeclaresCanonicalEditNavigation(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition(
                id: 'example-form',
                action: '/admin/example/create',
                csrf: false,
                navigateToEditAfterCreate: true,
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('data-lemonade-navigate-to-edit-after-create', $html);
    }

    public function testSaveBarUsesTheSharedDiscardAndActionContracts(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', actionUrl: '/admin/example/ajax', actionKey: 'save', csrf: false))
            ->saveBar(new AdminEditorSaveBarDefinition(
                primaryAction: new AdminEditorActionDefinition('Save changes', submit: true, primary: true),
                secondaryAction: new AdminEditorActionDefinition('Discard'),
                title: 'Unsaved changes',
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('data-lemonade-save-bar hidden', $html);
        self::assertStringContainsString('data-lemonade-editor-discard', $html);
        self::assertStringContainsString('data-lemonade-form="example-form"', $html);
        self::assertStringContainsString('data-lemonade-action-key="save"', $html);
    }

    public function testSaveBarUsesSharedUnsavedChangesTranslations(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->saveBar(new AdminEditorSaveBarDefinition(
                primaryAction: new AdminEditorActionDefinition('Save changes', submit: true, primary: true),
                titleKey: 'admin.editor.unsaved_changes',
                descriptionKey: 'admin.editor.unsaved_changes_help',
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('data-lemonade-i18n="admin.editor.unsaved_changes"', $html);
        self::assertStringContainsString('data-lemonade-i18n="admin.editor.unsaved_changes_help"', $html);
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

    public function testBreadcrumbLabelsUseTheClientTranslationWrapper(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->header(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition('', breadcrumbs: [
                ['label' => '', 'labelKey' => 'example.module.name', 'href' => '/admin/example'],
                ['label' => '', 'labelKey' => 'example.editor.title', 'current' => true],
            ]))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('<a href="/admin/example" data-lemonade-i18n="example.module.name">', $html);
        self::assertStringContainsString('<span data-lemonade-i18n="example.editor.title"></span>', $html);
    }

    public function testHeaderContextRendersEscapedInternalNavigationItems(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->header(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition(
                'Example',
                context: new AdminEditorHeaderContextDefinition('Content language', [
                    new AdminEditorHeaderContextItem('English', '/admin/example?locale=en', active: true),
                    new AdminEditorHeaderContextItem('Deutsch', '/admin/example?locale=de', secondary: '<New>'),
                ]),
                actions: [new AdminEditorActionDefinition('Save', submit: true, primary: true)],
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('<div class="admin-editor-header-title-context">', $html);
        self::assertStringContainsString('<button class="btn btn-light lm-editor-header-context-trigger"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertStringContainsString('aria-label="Content language"', $html);
        self::assertStringContainsString('<span>English</span><i class="bi bi-chevron-down" aria-hidden="true"></i>', $html);
        self::assertStringNotContainsString('Content language: English', $html);
        self::assertStringContainsString('data-lemonade-dropdown-panel hidden', $html);
        self::assertStringContainsString('href="/admin/example?locale=en"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('&lt;New&gt;', $html);
        self::assertLessThan(
            strpos($html, '<div class="d-flex gap-2">'),
            strpos($html, '<div class="admin-editor-header-title-context">'),
        );
    }

    public function testHeaderWithoutContextKeepsTheExistingTitleAndActionsMarkup(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->header(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition(
                'Example',
                actions: [new AdminEditorActionDefinition('Back', href: '/admin/example')],
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('<header class="page-heading"><div class="admin-editor-header-main"><h1>Example</h1></div><div class="d-flex gap-2">', $html);
        self::assertStringNotContainsString('admin-editor-header-title-context', $html);
        self::assertStringNotContainsString('lm-editor-header-context', $html);
    }

    public function testHeaderDoesNotRenderASingletonContextControl(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->header(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorHeaderDefinition(
                'Example',
                context: new AdminEditorHeaderContextDefinition('Content language', [
                    new AdminEditorHeaderContextItem('English', '/admin/example?locale=en', active: true),
                ]),
            ))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('<h1>Example</h1>', $html);
        self::assertStringNotContainsString('admin-editor-header-title-context', $html);
        self::assertStringNotContainsString('lm-editor-header-context', $html);
        self::assertStringNotContainsString('Content language', $html);
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
        self::assertStringContainsString('id="example-editor-section-details"', $html);
    }

    public function testNestedTabGroupUsesTheSharedTabsContract(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->tab(new AdminEditorTab('language-cs', 'Čeština', [
                new TabGroupBlock('content-language-cs', [
                    new AdminEditorTab('content', 'Content', [
                        new SectionBlock('content', [AdminEditorFieldDefinition::text('title', 'Title')], 'Content'),
                    ], default: true),
                    new AdminEditorTab('seo', 'SEO', [
                        new SectionBlock('seo', [AdminEditorFieldDefinition::text('page_title', 'Page title')], 'SEO'),
                    ]),
                ]),
            ], default: true))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('data-lemonade-tabs', $html);
        self::assertStringContainsString('id="example-editor-tab-group-content-language-cs-button-content"', $html);
        self::assertStringContainsString('aria-controls="example-editor-tab-group-content-language-cs-panel-seo"', $html);
    }

    public function testHiddenFieldInFieldGroupDoesNotRenderAVisibleColumn(): void
    {
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminEditorFormDefinition('example-form', '/admin/example', csrf: false))
            ->section(new SectionBlock('details', [
                new FieldGroupBlock([
                    new FieldColumn(
                        field: AdminEditorFieldDefinition::hidden('version'),
                        md: 6,
                    ),
                    new FieldColumn(
                        field: AdminEditorFieldDefinition::text('name', 'Name'),
                        md: 6,
                    ),
                ]),
            ]))
            ->build();

        $html = (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());

        self::assertStringContainsString('name="version" type="hidden"', $html);
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
