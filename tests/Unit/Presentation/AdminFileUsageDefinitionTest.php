<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Presentation\AdminFileCapabilities;
use Lemonade\Admin\Presentation\AdminFilePresentation;
use Lemonade\Admin\Presentation\AdminFileUsageDefinition;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Framework\Upload\Config\ImageUploadProfileConfig;
use PHPUnit\Framework\TestCase;

/**
 * Overuje explicitni image profil pro polymorfni generic file usage
 */
final class AdminFileUsageDefinitionTest extends TestCase
{
    /**
     * Overi ze generic usage muze deklarovat samostatny image profil
     */
    public function testGenericUsageExposesOptionalImageProfile(): void
    {
        $definition = new AdminFileUsageDefinition(
            moduleCode: 'cms.news',
            usage: 'attachment',
            kind: 'file',
            profile: 'admin-file',
            multiple: true,
            sortable: true,
            imageProfile: 'admin-image',
        );

        self::assertSame('admin-image', $definition->imageProfile());
    }

    /**
     * Overi ze cisty image usage neprijima polymorfni image profil
     */
    public function testImageUsageRejectsPolymorphicImageProfile(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AdminFileUsageDefinition(
            moduleCode: 'cms.news',
            usage: 'gallery',
            kind: 'image',
            profile: 'admin-image',
            multiple: true,
            sortable: true,
            imageProfile: 'admin-image',
        );
    }

    /**
     * Overi ze rich-text files zachovaji polymorfni attachment kontrakt
     */
    public function testRichTextFilesUseThePolymorphicGenericFileContract(): void
    {
        $registry = new AdminFileUsageRegistry();
        $registry->registerCapabilities('cms.news', new AdminFileCapabilities(richTextFiles: true));

        $definition = $registry->richTextFile('cms.news');

        self::assertSame('content_file', $definition->usage());
        self::assertSame('file', $definition->kind());
        self::assertSame('admin-image', $definition->imageProfile());
    }

    /**
     * Overuje ze image attachment zvoli image profil uz pred chunk session
     */
    public function testFileUsageSelectsImagePipelineForAllowedImageExtension(): void
    {
        self::assertSame(
            ['kind' => 'image', 'profile' => 'admin-image'],
            $this->attachmentDefinition()->uploadPipeline('gallery.PnG', $this->imageProfile()),
        );
    }

    /**
     * Overuje ze dokumentova priloha zustane v generic file pipeline
     */
    public function testFileUsageKeepsGenericPipelineForDocumentExtension(): void
    {
        self::assertSame(
            ['kind' => 'file', 'profile' => 'admin-file'],
            $this->attachmentDefinition()->uploadPipeline('document.docx', $this->imageProfile()),
        );
    }

    /**
     * Overuje stabilni profily thumbnailu, galerie a obou rich-text uploadu
     */
    public function testCmsCapabilitiesSelectTheirConfiguredPipelineBeforeChunkStart(): void
    {
        $registry = new AdminFileUsageRegistry();
        $registry->registerCapabilities('cms.news', new AdminFileCapabilities(
            thumbnail: AdminFilePresentation::Landscape,
            gallery: true,
            attachments: true,
            richTextImages: true,
            richTextFiles: true,
        ));

        self::assertSame(
            ['kind' => 'image', 'profile' => 'admin-image'],
            $registry->require('cms.news', 'thumbnail')->uploadPipeline('thumbnail.webp', null),
        );
        self::assertSame(
            ['kind' => 'image', 'profile' => 'admin-image'],
            $registry->require('cms.news', 'gallery')->uploadPipeline('gallery.jpg', null),
        );
        self::assertSame(
            ['kind' => 'image', 'profile' => 'admin-image'],
            $registry->richTextImage('cms.news')->uploadPipeline('content.png', null),
        );
        self::assertSame(
            ['kind' => 'image', 'profile' => 'admin-image'],
            $registry->richTextFile('cms.news')->uploadPipeline('inline.jpeg', $this->imageProfile()),
        );
        self::assertSame(
            ['kind' => 'file', 'profile' => 'admin-file'],
            $registry->richTextFile('cms.news')->uploadPipeline('attachment.pdf', $this->imageProfile()),
        );
    }

    /**
     * Vytvori polymorfni attachment usage pouzivanou CMS moduly
     */
    private function attachmentDefinition(): AdminFileUsageDefinition
    {
        return new AdminFileUsageDefinition(
            moduleCode: 'cms.news',
            usage: 'attachment',
            kind: 'file',
            profile: 'admin-file',
            multiple: true,
            sortable: true,
            imageProfile: 'admin-image',
        );
    }

    /**
     * Vytvori aktualni admin image profil pro rozhodnuti pred chunk session
     */
    private function imageProfile(): ImageUploadProfileConfig
    {
        return new ImageUploadProfileConfig(
            targetDirectory: 'admin/images',
            maxBytes: 5_242_880,
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            reencode: true,
            minWidth: null,
            maxWidth: null,
            minHeight: null,
            maxHeight: null,
        );
    }
}
