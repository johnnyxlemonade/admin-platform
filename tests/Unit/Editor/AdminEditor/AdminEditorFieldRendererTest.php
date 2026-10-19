<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Closure;
use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldCommon;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Presentation\AdminFileCapabilities;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\Presentation\AdminThumbnailComponent;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Upload\Config\FileUploadProfileConfig;
use Lemonade\Framework\Upload\Config\ImageUploadProfileConfig;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Image\ImageUrlGenerator;
use Lemonade\Image\ImageVariantRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminEditorFieldRendererTest extends TestCase
{
    /**
     * Overi rendering vsech podporovanych field types
     *
     * @param Closure(): AdminEditorFieldDefinition $factory
     */
    #[DataProvider('renderedFieldProvider')]
    public function testItRendersEverySupportedFieldType(Closure $factory, string $expectedHtml): void
    {
        $html = (new AdminEditorFieldRenderer())->render(
            $factory(),
            new AdminEditorRenderContext(),
            'contract-editor',
        );

        self::assertStringContainsString($expectedHtml, $html);
    }

    /**
     * Poskytne vsechny field types s jejich stabilnim HTML kontraktem
     *
     * @return iterable<string, array{Closure(): AdminEditorFieldDefinition, string}>
     */
    public static function renderedFieldProvider(): iterable
    {
        yield 'text' => [static fn(): AdminEditorFieldDefinition => Field::text('field'), 'type="text"'];
        yield 'email' => [static fn(): AdminEditorFieldDefinition => Field::email('field'), 'type="email"'];
        yield 'tel' => [static fn(): AdminEditorFieldDefinition => Field::tel('field'), 'type="tel"'];
        yield 'number' => [static fn(): AdminEditorFieldDefinition => Field::number('field'), 'type="number"'];
        yield 'datetime local' => [static fn(): AdminEditorFieldDefinition => Field::datetimeLocal('field'), 'type="datetime-local"'];
        yield 'textarea' => [static fn(): AdminEditorFieldDefinition => Field::textarea('field'), '<textarea'];
        yield 'select' => [static fn(): AdminEditorFieldDefinition => Field::select('field'), '<select'];
        yield 'checkbox' => [static fn(): AdminEditorFieldDefinition => Field::checkbox('field'), 'type="checkbox"'];
        yield 'toggle' => [static fn(): AdminEditorFieldDefinition => Field::toggle('field'), 'form-switch'];
        yield 'hidden' => [static fn(): AdminEditorFieldDefinition => Field::hidden('field'), 'type="hidden"'];
        yield 'readonly display' => [static fn(): AdminEditorFieldDefinition => Field::readonlyDisplay('field'), 'form-control-plaintext'];
        yield 'status display' => [static fn(): AdminEditorFieldDefinition => Field::statusDisplay('field'), 'admin-editor-status-display'];
        yield 'readonly editable' => [static fn(): AdminEditorFieldDefinition => Field::readonlyEditable('field'), 'data-lemonade-editable-field-root'];
        yield 'URL segment' => [static fn(): AdminEditorFieldDefinition => Field::urlSegment('field'), 'data-lemonade-url-segment'];
    }

    public function testItRendersLabelHelpErrorAndRequiredState(): void
    {
        $field = Field::text('title', 'Title')->required()->help('Enter a title');
        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(errors: ['title' => 'Title is required']), 'article-editor');

        self::assertStringContainsString('Title is required', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
    }

    public function testOldInputHasPriorityOverContextAndDefaultValue(): void
    {
        $field = Field::text('title', 'Title')->defaultValue('Default');
        $context = new AdminEditorRenderContext(values: ['title' => 'Loaded'], oldInput: ['title' => 'Old input']);

        $html = (new AdminEditorFieldRenderer())->render($field, $context, 'article-editor');

        self::assertStringContainsString('value="Old input"', $html);
        self::assertStringNotContainsString('value="Loaded"', $html);
    }

    /**
     * Overi celou prioritu old input, contextu a strukturalniho defaultu
     */
    public function testValuePrecedenceRemainsStable(): void
    {
        $renderer = new AdminEditorFieldRenderer();

        $oldInput = $renderer->render(
            Field::text('title')->defaultValue('Default'),
            new AdminEditorRenderContext(values: ['title' => 'Loaded'], oldInput: ['title' => 'Old input']),
            'editor',
        );
        $loaded = $renderer->render(
            Field::text('title')->defaultValue('Default'),
            new AdminEditorRenderContext(values: ['title' => 'Loaded']),
            'editor',
        );
        $default = $renderer->render(
            Field::text('title')->defaultValue('Default'),
            new AdminEditorRenderContext(),
            'editor',
        );

        self::assertStringContainsString('value="Old input"', $oldInput);
        self::assertStringContainsString('value="Loaded"', $loaded);
        self::assertStringContainsString('value="Default"', $default);
    }

    /**
     * Overi ze explicitni null v runtime kontextu zustava autoritativni vuci defaultu
     */
    public function testExplicitNullContextValueOverridesDefaultValue(): void
    {
        $field = Field::text('title')->defaultValue('Default');
        $context = new AdminEditorRenderContext(values: ['title' => null]);

        $html = (new AdminEditorFieldRenderer())->render($field, $context, 'article-editor');

        self::assertStringContainsString('value=""', $html);
        self::assertStringNotContainsString('value="Default"', $html);
    }

    public function testReadonlyInputRendersExplicitAutocompleteWithoutHtmlRequiredOrDisabled(): void
    {
        $field = Field::email('email', 'Email')
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
        $field = Field::email('email', 'Email')->required();

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(), 'user-editor');

        self::assertStringContainsString(' required', $html);
        self::assertStringNotContainsString(' readonly', $html);
        self::assertStringNotContainsString(' disabled', $html);
        self::assertStringNotContainsString('autocomplete=', $html);
    }

    /**
     * Datetime field renderuje native control s canonical hodnotou bez sekund
     */
    public function testDatetimeLocalRendersCanonicalDatabaseValue(): void
    {
        $field = Field::datetimeLocal('published_at', 'Published from');

        $html = (new AdminEditorFieldRenderer())->render(
            $field,
            new AdminEditorRenderContext(values: ['published_at' => '2026-10-13 14:30:45']),
            'article-editor',
        );

        self::assertStringContainsString('type="datetime-local"', $html);
        self::assertStringContainsString('value="2026-10-13T14:30"', $html);
    }

    public function testReadonlySelectUsesDisabledControlAndPreservesSubmittedValue(): void
    {
        $field = Field::select('status', 'Status')
            ->options(['draft' => 'Draft', 'published' => 'Published'])
            ->readonly();

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(values: ['status' => 'draft']), 'article-editor');

        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString(' disabled', $html);
        self::assertStringContainsString('name="status"', $html);
        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('value="draft"', $html);
    }

    public function testDisabledSelectDoesNotAddPreservedValue(): void
    {
        $field = Field::select('status', 'Status')
            ->options(['draft' => 'Draft'])
            ->disabled();

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(values: ['status' => 'draft']), 'article-editor');

        self::assertStringNotContainsString('name="status" type="hidden"', $html);
    }

    public function testMultipleSelectUsesArrayTransportAndSelectedValues(): void
    {
        $field = Field::select('tags', 'Tags')
            ->multiple()
            ->options(['Culture' => 'Culture', 'Sport' => 'Sport']);

        $html = (new AdminEditorFieldRenderer())->render($field, new AdminEditorRenderContext(values: ['tags' => ['Sport']]), 'article-editor');

        self::assertStringContainsString('name="tags[]"', $html);
        self::assertStringContainsString(' multiple', $html);
        self::assertStringContainsString('value="Sport"', $html);
        self::assertStringNotContainsString('value="Culture" selected', $html);
    }

    /**
     * URL segment zustava editovatelny a pouziva samostatnou module action
     */
    public function testUrlSegmentRendersParentContextAndIndependentAction(): void
    {
        $field = Field::urlSegment('url_segment', 'URL segment')
            ->prefix('/parent/')
            ->source('title', autoGenerate: true)
            ->action('change-url', 'Save URL');
        $form = new AdminActionFormDefinition(
            id: 'page-editor',
            action: '/admin/pages/edit/7',
            actionUrl: '/admin/pages/ajax/7',
            actionKey: 'save',
        );

        $html = (new AdminEditorFieldRenderer())->render(
            $field,
            new AdminEditorRenderContext(values: ['url_segment' => 'child']),
            'page-editor',
            $form,
        );

        self::assertStringContainsString('data-lemonade-url-segment', $html);
        self::assertStringContainsString('data-lemonade-transport-only', $html);
        self::assertStringContainsString('data-lemonade-action-key="change-url"', $html);
        self::assertStringContainsString('data-lemonade-url="/admin/pages/ajax/7"', $html);
        self::assertStringContainsString('>/parent/</span>', $html);
        self::assertStringContainsString('value="child"', $html);
        self::assertSame('admin.url_segment.label', $field->labelTranslationKey());
        self::assertSame('admin.url_segment.help', $field->helpTranslationKey());
        self::assertSame('admin.url_segment.save', $field->actionTranslationKey());
        self::assertStringNotContainsString(' readonly', $html);
        self::assertStringNotContainsString('data-lemonade-editable-field-unlock', $html);
    }

    /**
     * Overi exactni HTML kontrakt reprezentativnich fieldu pred a po refactoringu
     */
    public function testRepresentativeHtmlMatchesPreRefactorContract(): void
    {
        $renderer = new AdminEditorFieldRenderer();
        $context = new AdminEditorRenderContext(values: [
            'title' => 'Loaded',
            'tags' => ['b'],
            'content' => '<p>Body</p>',
            'slug' => 'child',
            'status' => 'Published',
        ]);
        $fields = [
            'text' => Field::text('title', 'Title')->required()->help('Help')->attributes(['maxlength' => '180']),
            'select' => Field::select('tags', 'Tags')->options(['a' => 'A', 'b' => 'B'])->multiple(),
            'textarea' => Field::textarea('content', 'Content')->richText(),
            'url' => Field::urlSegment('slug', 'Slug')->prefix('/parent/')->source('title', true),
            'status' => Field::statusDisplay('status', 'Status'),
        ];
        $expectedHashes = [
            'text' => '87135d7e042b3fe136a050e6cfaa2f06fd7648393ab3dd1feff57e0b75bacca1',
            'select' => 'fd285d04b59c124555a86ec6989608680afd4dff1b4df95c7bef1cc2f9222027',
            'textarea' => '8e3d19fb127b3c1310b1fe4b2a3dd29d8e80dda490061e1dbc744c3bec09cd1d',
            'url' => '76f5100e7bcbdaaa1793583eab97a486c1241c432fd6efdb61170e1cc3ba806a',
            'status' => '296d9df3c09277aa75a7806336c9491b30aab912e6d64ab72eabc62c234b55ff',
        ];

        foreach ($fields as $name => $field) {
            self::assertSame($expectedHashes[$name], hash('sha256', $renderer->render($field, $context, 'parity-editor')), $name);
        }
    }

    /**
     * Overi ze upload capability vyzaduje scoped upload komponentu pouze s runtime targetem
     */
    public function testRichTextUploadRequiresUploadComponentForEntityContext(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rich-text image upload requires AdminFileUploadComponent.');

        (new AdminEditorFieldRenderer())->render(
            Field::textarea('content')->richText(imageUpload: true, fileUpload: true),
            new AdminEditorRenderContext(moduleCode: 'cms.news', entityId: 7),
            'article-editor',
        );
    }

    /**
     * Overi zachovani obou rich-text upload transport atributu bez databaze
     */
    public function testRichTextUploadsRenderBothScopedTransportTargets(): void
    {
        $html = (new AdminEditorFieldRenderer(files: $this->fileUploads()))->render(
            Field::textarea('content')->richText(imageUpload: true, fileUpload: true),
            new AdminEditorRenderContext(moduleCode: 'cms.news', entityId: 7),
            'article-editor',
        );

        self::assertStringContainsString('data-lemonade-rich-text-upload-target="', $html);
        self::assertStringContainsString('data-lemonade-rich-text-file-upload-target="', $html);
        self::assertStringContainsString('&quot;module&quot;:&quot;cms.news&quot;', $html);
        self::assertStringContainsString('&quot;entity&quot;:7', $html);
        self::assertStringContainsString('&quot;usage&quot;:&quot;content_image&quot;', $html);
        self::assertStringContainsString('&quot;usage&quot;:&quot;content_file&quot;', $html);
    }

    /**
     * Overi explicitni selhani pro neznamy konkretni field
     */
    public function testUnknownFieldFailsExplicitly(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported AdminEditor field');

        (new AdminEditorFieldRenderer())->render(
            UnsupportedAdminEditorField::create('unknown'),
            new AdminEditorRenderContext(),
            'article-editor',
        );
    }

    /**
     * Vytvori DB-free upload presentation pro rendererovy kontrakt
     */
    private function fileUploads(): AdminFileUploadComponent
    {
        $router = new Router();
        $action = ControllerAction::for('TestController', 'handle');
        $router->getNamed('admin.file.collection.index', '/admin/files/{module}/{usage}/{entity}', $action);
        $router->postNamed('admin.file.collection.remove', '/admin/files/{module}/{usage}/{entity}/{file}/remove', $action);
        $router->postNamed('admin.file.chunk.start', '/admin/files/{module}/{usage}/{entity}/chunks', $action);
        $router->postNamed('admin.file.chunk.append', '/admin/files/chunks/{uploadId}', $action);
        $router->postNamed('admin.file.chunk.complete', '/admin/files/chunks/{uploadId}/complete', $action);
        $router->postNamed('admin.file.chunk.abort', '/admin/files/chunks/{uploadId}/abort', $action);
        $router->getNamed('api.file.download', '/files/{file}', $action);
        $urls = new UrlGenerator($router);
        $usages = new AdminFileUsageRegistry();
        $usages->registerCapabilities('cms.news', new AdminFileCapabilities(richTextImages: true, richTextFiles: true));
        $images = new ImageUrlGenerator($urls, new ImageVariantRegistry());

        return new AdminFileUploadComponent(
            new AdminFileModel(self::createStub(DatabaseDriverInterface::class)),
            new AdminThumbnailComponent($images),
            $images,
            $urls,
            $usages,
            new UploadConfig(
                files: [
                    'admin-file' => new FileUploadProfileConfig('/tmp', 5_000_000, ['pdf']),
                ],
                images: [
                    'admin-image' => new ImageUploadProfileConfig('/tmp', 5_000_000, ['jpg'], true, null, null, null, null),
                ],
            ),
        );
    }
}

/**
 * Poskytuje neznamy field pro overeni explicitniho renderer dispatch
 */
final readonly class UnsupportedAdminEditorField extends AdminEditorFieldDefinition
{
    /**
     * Nastavi spolecnou konfiguraci testovaciho fieldu
     */
    private function __construct(AdminEditorFieldCommon $common)
    {
        parent::__construct($common);
    }

    /**
     * Vytvori testovaci field
     */
    public static function create(string $name): self
    {
        return new self(AdminEditorFieldCommon::create($name));
    }

    /**
     * Vytvori testovaci field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common);
    }
}
