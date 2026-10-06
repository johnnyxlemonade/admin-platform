<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final class AdminEditorRenderContext
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, mixed> $values
     * @param array<string, mixed> $oldInput
     * @param array<string, string|list<string>> $errors
     * @param array<string, scalar|bool|null> $uiFlags
     */
    public function __construct(
        private readonly array $values = [],
        private readonly array $oldInput = [],
        private readonly array $errors = [],
        private readonly string $mode = 'edit',
        private readonly array $uiFlags = [],
    ) {}

    /**
     * Zpracovava hodnotu values v konfiguraci editoru
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Zpracovava hodnotu oldinput v konfiguraci editoru
     * @return array<string, mixed>
     */
    public function oldInput(): array
    {
        return $this->oldInput;
    }

    /**
     * Zpracovava hodnotu errors v konfiguraci editoru
     * @return array<string, string|list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Zpracovava hodnotu mode v konfiguraci editoru
     */
    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Zpracovava hodnotu uiflags v konfiguraci editoru
     * @return array<string, scalar|bool|null>
     */
    public function uiFlags(): array
    {
        return $this->uiFlags;
    }

    /**
     * Zpracovava hodnotu errorsfor v konfiguraci editoru
     * @return list<string>
     */
    public function errorsFor(string $field): array
    {
        $errors = $this->errors[$field] ?? [];
        return is_array($errors) ? array_values($errors) : [$errors];
    }
}
