<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditorViewRenderer;
use Lemonade\Framework\View\ViewRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

/**
 * Overuje requestove doplneni editoroveho rendereru do view dat
 */
final class AdminEditorViewRendererTest extends TestCase
{
    /**
     * Overuje vychozi doplneni pro render i content
     */
    public function testItAddsScopedFieldRendererToBothRenderingPaths(): void
    {
        $fields = (new ReflectionClass(AdminEditorFieldRenderer::class))->newInstanceWithoutConstructor();
        $response = $this->createMock(ResponseInterface::class);
        $inner = $this->createMock(ViewRendererInterface::class);
        $inner->expects(self::once())->method('render')->with('admin::page', ['value' => 1, 'adminEditorFields' => $fields], 201)->willReturn($response);
        $inner->expects(self::once())->method('content')->with('admin::partial', ['adminEditorFields' => $fields])->willReturn('content');
        $renderer = new AdminEditorViewRenderer($inner, $fields);

        self::assertSame($response, $renderer->render('admin::page', ['value' => 1], 201));
        self::assertSame('content', $renderer->content('admin::partial'));
    }

    /**
     * Overuje autoritu explicitne predanych view dat
     */
    public function testExplicitFieldRendererIsNotOverwritten(): void
    {
        $default = (new ReflectionClass(AdminEditorFieldRenderer::class))->newInstanceWithoutConstructor();
        $explicit = (new ReflectionClass(AdminEditorFieldRenderer::class))->newInstanceWithoutConstructor();
        $inner = $this->createMock(ViewRendererInterface::class);
        $inner->expects(self::once())->method('content')->with('admin::partial', ['adminEditorFields' => $explicit])->willReturn('content');

        self::assertSame(
            'content',
            (new AdminEditorViewRenderer($inner, $default))->content('admin::partial', ['adminEditorFields' => $explicit]),
        );
    }
}
