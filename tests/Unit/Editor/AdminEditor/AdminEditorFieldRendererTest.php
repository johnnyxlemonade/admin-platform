<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldCommon;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AdminEditorFieldRendererTest extends TestCase
{
    /**
     * Vlastni ID zachova vazbu labelu a napovedy bez duplicitnich atributu
     */
    public function testPresentationAttributesKeepAccessibleControlIdentity(): void
    {
        $html = (new AdminEditorFieldRenderer())->render(
            Field::text('location', 'Location')->help('Help')->attributes(['id' => 'workplace', 'class' => 'custom wide']),
            new AdminEditorRenderContext(values: ['location' => 'Prague']),
            'record',
        );
        $document = new \DOMDocument();
        $document->loadHTML($html);
        $input = $document->getElementsByTagName('input')->item(0);
        self::assertNotNull($input);
        self::assertSame('workplace', $input->getAttribute('id'));
        self::assertSame('workplace', $document->getElementsByTagName('label')->item(0)?->getAttribute('for'));
        self::assertSame('workplace-help', $input->getAttribute('aria-describedby'));
        self::assertSame('location', $input->getAttribute('name'));
        self::assertSame('Prague', $input->getAttribute('value'));
        self::assertSame(['form-control', 'custom', 'wide'], explode(' ', $input->getAttribute('class')));
    }

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
}

final readonly class UnsupportedAdminEditorField extends AdminEditorFieldDefinition
{
    private function __construct(AdminEditorFieldCommon $common)
    {
        parent::__construct($common);
    }

    public static function create(string $name): self
    {
        return new self(AdminEditorFieldCommon::create($name));
    }

    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common);
    }
}
