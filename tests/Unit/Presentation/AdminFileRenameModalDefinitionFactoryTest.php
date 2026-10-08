<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
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
            self::assertSame('text', $fields['display_name']->type());
            self::assertTrue($fields['display_name']->isRequired());
            self::assertSame(['maxlength' => '255'], $fields['display_name']->inputAttributes());
            self::assertSame('textarea', $fields['caption']->type());
            self::assertFalse($fields['caption']->isRequired());
            self::assertSame('admin.file_upload.caption', $fields['caption']->labelKey());
            self::assertSame('admin.file_upload.caption_optional', $fields['caption']->helpKey());
            self::assertSame(['maxlength' => '500', 'rows' => '3'], $fields['caption']->inputAttributes());
        }
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

        return new AdminFileRenameModalDefinitionFactory(new UrlGenerator(router: $router));
    }
}
