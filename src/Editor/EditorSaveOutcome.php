<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use Lemonade\Framework\Validation\ValidationResult;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final class EditorSaveOutcome
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, mixed> $input
     */
    private function __construct(
        private readonly ?EditorSaveResult $result,
        private readonly ?ValidationResult $validation,
        private readonly array $input,
    ) {}

    /**
     * Vytvari hodnotu pro saved
     */
    public static function saved(EditorSaveResult $result): self
    {
        return new self($result, null, []);
    }

    /**
     * Vytvari hodnotu pro invalid
     * @param array<string, mixed> $input
     */
    public static function invalid(ValidationResult $validation, array $input): self
    {
        return new self(null, $validation, $input);
    }

    /**
     * Rozhoduje stav issaved
     */
    public function isSaved(): bool
    {
        return $this->result !== null;
    }

    /**
     * Zpracovava hodnotu result v konfiguraci editoru
     */
    public function result(): EditorSaveResult
    {
        if ($this->result === null) {
            throw new \LogicException('Editor save result is unavailable for invalid input.');
        }

        return $this->result;
    }

    /**
     * Zpracovava hodnotu validation v konfiguraci editoru
     */
    public function validation(): ValidationResult
    {
        if ($this->validation === null) {
            throw new \LogicException('Validation result is unavailable after a successful save.');
        }

        return $this->validation;
    }

    /**
     * Zpracovava hodnotu input v konfiguraci editoru
     * @return array<string, mixed>
     */
    public function input(): array
    {
        return $this->input;
    }
}
