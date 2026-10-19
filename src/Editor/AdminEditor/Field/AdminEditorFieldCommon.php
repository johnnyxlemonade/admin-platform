<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use InvalidArgumentException;

/**
 * Drzi malou spolecnou konfiguraci identity a textu editoroveho pole
 */
final readonly class AdminEditorFieldCommon
{
    /**
     * Nastavi vsechny spolecne hodnoty pole
     */
    private function __construct(
        private string $name,
        private string $label,
        private ?string $labelKey,
        private ?string $help,
        private ?string $helpKey,
        private mixed $defaultValue,
        private bool $hasDefaultValue,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Field name must not be empty.');
        }
    }

    /**
     * Vytvori spolecnou konfiguraci pro nove pole
     */
    public static function create(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self($name, $label, $labelKey, null, null, null, false);
    }

    /**
     * Vrati transportni nazev pole
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Vrati zalozni text labelu
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Vrati prekladovy klic labelu
     */
    public function labelKey(): ?string
    {
        return $this->labelKey;
    }

    /**
     * Vrati zalozni text napovedy
     */
    public function help(): ?string
    {
        return $this->help;
    }

    /**
     * Vrati prekladovy klic napovedy
     */
    public function helpKey(): ?string
    {
        return $this->helpKey;
    }

    /**
     * Vrati strukturalni default pole
     */
    public function defaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Rozhodne zda byl strukturalni default explicitne nastaven
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    /**
     * Vytvori konfiguraci s novym textem labelu
     */
    public function withLabel(string $label): self
    {
        return new self($this->name, $label, $this->labelKey, $this->help, $this->helpKey, $this->defaultValue, $this->hasDefaultValue);
    }

    /**
     * Vytvori konfiguraci s novym prekladovym klicem labelu
     */
    public function withLabelKey(?string $labelKey): self
    {
        return new self($this->name, $this->label, $labelKey, $this->help, $this->helpKey, $this->defaultValue, $this->hasDefaultValue);
    }

    /**
     * Vytvori konfiguraci s novou napovedou
     */
    public function withHelp(string $help, ?string $helpKey): self
    {
        return new self($this->name, $this->label, $this->labelKey, $help, $helpKey, $this->defaultValue, $this->hasDefaultValue);
    }

    /**
     * Vytvori konfiguraci s novym prekladovym klicem napovedy
     */
    public function withHelpKey(?string $helpKey): self
    {
        return new self($this->name, $this->label, $this->labelKey, $this->help ?? '', $helpKey, $this->defaultValue, $this->hasDefaultValue);
    }

    /**
     * Vytvori konfiguraci s explicitnim strukturalnim defaultem
     */
    public function withDefaultValue(mixed $defaultValue): self
    {
        return new self($this->name, $this->label, $this->labelKey, $this->help, $this->helpKey, $defaultValue, true);
    }
}
