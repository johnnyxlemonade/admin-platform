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
