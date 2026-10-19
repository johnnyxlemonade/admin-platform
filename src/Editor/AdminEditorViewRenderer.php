<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Doplni requestovy renderer o scoped renderer editorovych poli
 */
final readonly class AdminEditorViewRenderer implements ViewRendererInterface
{
    /**
     * Nastavuje vnitrni renderer a scoped editorovy renderer
     */
    public function __construct(
        private ViewRendererInterface $inner,
        private AdminEditorFieldRenderer $fields,
    ) {}

    public function render(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->inner->render($template, $this->data($data), $status);
    }

    public function content(string $template, array $data = []): string
    {
        return $this->inner->content($template, $this->data($data));
    }

    /**
     * Doplni vychozi data bez prepsani explicitni hodnoty
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function data(array $data): array
    {
        return $data + ['adminEditorFields' => $this->fields];
    }
}
