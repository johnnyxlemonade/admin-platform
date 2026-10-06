<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use InvalidArgumentException;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use PHPUnit\Framework\TestCase;

final class AdminEditorBuilderTest extends TestCase
{
    public function testItBuildsSimpleEditorWithoutTabs(): void
    {
        $section = new SectionBlock('details', [AdminEditorFieldDefinition::text('name', 'Name')], 'Details');
        $editor = AdminEditorBuilder::create('system.languages')->form($this->form())->section($section)->build();

        self::assertSame('system.languages', $editor->id());
        self::assertSame([$section], $editor->blocks());
        self::assertSame([], $editor->tabs());
    }

    public function testItBuildsTabbedEditorWithDefaultTab(): void
    {
        $tab = new AdminEditorTab('basic', 'Basic', [], default: true);
        $editor = AdminEditorBuilder::create('system.users')->form($this->form())->tab($tab)->build();

        self::assertSame([$tab], $editor->tabs());
        self::assertTrue($editor->tabs()[0]->default());
    }

    public function testItRejectsDuplicateTabIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AdminEditorBuilder::create('system.users')->form($this->form())
            ->tab(new AdminEditorTab('basic', 'Basic', [], default: true))
            ->tab(new AdminEditorTab('basic', 'Access', []))
            ->build();
    }

    public function testItRejectsMultipleDefaultTabs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AdminEditorBuilder::create('system.users')->form($this->form())
            ->tab(new AdminEditorTab('basic', 'Basic', [], default: true))
            ->tab(new AdminEditorTab('access', 'Access', [], default: true))
            ->build();
    }

    public function testItRejectsDuplicateSectionIdsAcrossTabsAndSidebar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $section = new SectionBlock('publication', [], 'Publication');
        AdminEditorBuilder::create('cms.news')
            ->form($this->form())
            ->tab(new AdminEditorTab('content', 'Content', [$section], default: true))
            ->sidebar(new \Lemonade\Admin\Editor\AdminEditor\AdminEditorSidebarDefinition([
                new SectionBlock('publication', [], 'Publication'),
            ]))
            ->build();
    }

    public function testSectionHoldsFieldAndCustomViewKeepsExplicitContext(): void
    {
        $field = AdminEditorFieldDefinition::text('title', 'Title');
        $custom = new CustomViewBlock('news::editor.content', ['articleId' => 42]);
        $section = new SectionBlock('content', [$field, $custom], 'Content');

        self::assertSame([$field, $custom], $section->blocks());
        self::assertSame('news::editor.content', $custom->view());
        self::assertSame(['articleId' => 42], $custom->context());
    }

    private function form(): AdminEditorFormDefinition
    {
        return new AdminEditorFormDefinition('editor-form', '/admin/example', csrf: false);
    }
}
