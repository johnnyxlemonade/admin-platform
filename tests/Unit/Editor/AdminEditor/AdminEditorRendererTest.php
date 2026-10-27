<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBlock;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderer;
use PHPUnit\Framework\TestCase;

final class AdminEditorRendererTest extends TestCase
{
    public function testFieldColumnRejectsInvalidBootstrapSpan(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FieldColumn(
            field: Field::text('name', 'Name'),
            md: 13,
        );
    }

    /**
     * Overi explicitni selhani pro neznamy block kontrakt
     */
    public function testUnknownBlockIsNotSilentlyIgnored(): void
    {
        $block = new readonly class implements AdminEditorBlock {};
        $editor = AdminEditorBuilder::create('example-editor')
            ->form(new AdminActionFormDefinition('example-form', '/admin/example', csrf: false))
            ->block($block)
            ->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported AdminEditor block');

        (new AdminEditorRenderer())->render($editor, new AdminEditorRenderContext());
    }
}
