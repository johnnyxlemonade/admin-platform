<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class EditorSaveResult
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
        private string $messageKey,
    ) {}

    /**
     * Zpracovava hodnotu data v konfiguraci editoru
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Zpracovava hodnotu messagekey v konfiguraci editoru
     */
    public function messageKey(): string
    {
        return $this->messageKey;
    }
}
