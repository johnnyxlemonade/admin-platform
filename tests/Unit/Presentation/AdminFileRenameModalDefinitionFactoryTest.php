<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\TextareaField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextInputField;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Admin\Presentation\AdminFileRenameModalDefinitionFactory;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class AdminFileRenameModalDefinitionFactoryTest extends TestCase
{
    public function testBuildsSharedPresentationEditorForFeaturedImageGalleryAndAttachments(): void
    {
        foreach (['thumbnail', 'gallery', 'attachment'] as $usage) {
            $editor = $this->factory()->modal('cms.news', $usage, 7, 14);
            $fields = $this->fields($editor);

            self::assertSame('admin.file.presentation', $editor->id());
            self::assertSame('/admin/files/cms.news/' . $usage . '/7/14/rename', $editor->form()->action());
            self::assertSame('edit', $editor->form()->actionKey());
            $displayName = $fields['display_name'];
            $caption = $fields['caption'];
            self::assertInstanceOf(TextInputField::class, $displayName);
            self::assertTrue($displayName->isRequired());
            self::assertSame(['maxlength' => '255'], $displayName->inputAttributes());
            self::assertInstanceOf(TextareaField::class, $caption);
            self::assertFalse($caption->isRequired());
            self::assertSame('admin.file_upload.caption', $caption->labelTranslationKey());
            self::assertSame('admin.file_upload.caption_optional', $caption->helpTranslationKey());
            self::assertSame(['maxlength' => '500', 'rows' => '3'], $caption->inputAttributes());
        }
    }

    public function testBuildsTheSamePresentationEditorForTheCentralMediaCatalog(): void
    {
        $editor = $this->factory()->mediaModal(14);

        self::assertSame('admin.file.presentation', $editor->id());
        self::assertSame('/admin/system/media/14/presentation', $editor->form()->action());
        self::assertSame('edit', $editor->form()->actionKey());
        self::assertArrayHasKey('display_name', $this->fields($editor));
        self::assertArrayHasKey('caption', $this->fields($editor));
    }

    /**
     * @return array<string, AdminEditorFieldDefinition>
     */
    private function fields(AdminEditorDefinition $editor): array
    {
        $section = $editor->blocks()[0];
        self::assertInstanceOf(SectionBlock::class, $section);
        $fields = [];
        foreach ($section->blocks() as $block) {
            self::assertInstanceOf(AdminEditorFieldDefinition::class, $block);
            $fields[$block->name()] = $block;
        }

        return $fields;
    }

    private function factory(): AdminFileRenameModalDefinitionFactory
    {
        $router = new Router();
        $router->postNamed(
            name: 'admin.file.collection.rename',
            path: '/admin/files/{module}/{usage}/{entity}/{file}/rename',
            action: ControllerAction::for('TestController', 'rename'),
        );
        $router->postNamed(
            name: 'admin.media.presentation.save',
            path: '/admin/system/media/{file}/presentation',
            action: ControllerAction::for('TestController', 'savePresentation'),
        );

        return new AdminFileRenameModalDefinitionFactory(new UrlGenerator(router: $router));
    }
}
