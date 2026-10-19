<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Rendering;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class AdminEditorRenderContext
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, mixed> $values
     * @param array<string, mixed> $oldInput
     * @param array<string, string|list<string>> $errors
     */
    public function __construct(
        private array $values = [],
        private array $oldInput = [],
        private array $errors = [],
        private string $mode = 'edit',
        private ?string $moduleCode = null,
        private ?int $entityId = null,
    ) {
        if (($this->moduleCode === null) !== ($this->entityId === null)) {
            throw new \InvalidArgumentException('Module code and entity ID must be provided together.');
        }
        if ($this->moduleCode !== null && trim($this->moduleCode) === '') {
            throw new \InvalidArgumentException('Module code must not be empty.');
        }
        if ($this->entityId !== null && $this->entityId <= 0) {
            throw new \InvalidArgumentException('Entity ID must be positive.');
        }
    }

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
     * Vraci modul vlastnici renderovany entity context
     */
    public function moduleCode(): ?string
    {
        return $this->moduleCode;
    }

    /**
     * Vraci identitu renderovane entity pro modulove capability
     */
    public function entityId(): ?int
    {
        return $this->entityId;
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
