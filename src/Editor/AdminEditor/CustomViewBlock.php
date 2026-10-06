<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class CustomViewBlock implements AdminEditorBlock
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, mixed> $context
     */
    public function __construct(private string $view, private array $context = [])
    {
        if (trim($view) === '') {
            throw new InvalidArgumentException('Custom view path must not be empty.');
        }
    }

    /**
     * Zpracovava hodnotu view v konfiguraci editoru
     */
    public function view(): string
    {
        return $this->view;
    }

    /**
     * Zpracovava hodnotu context v konfiguraci editoru
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
