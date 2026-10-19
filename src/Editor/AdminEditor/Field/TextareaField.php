<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use InvalidArgumentException;

/**
 * Definuje plain nebo rich-text textarea vcetne upload capabilities
 */
final readonly class TextareaField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi presne typovanou konfiguraci textarea
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private ?string $placeholder,
        private ?string $placeholderKey,
        private bool $richText,
        private bool $imageUpload,
        private bool $fileUpload,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori textarea z factory facade
     */
    public static function create(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), new AdminEditorInputState(), null, null, false, false, false);
    }

    /**
     * Vrati zalozni placeholder
     */
    public function placeholderText(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Vrati prekladovy klic placeholderu
     */
    public function placeholderKey(): ?string
    {
        return $this->placeholderKey;
    }

    /**
     * Rozhodne zda textarea pouziva rich-text editor
     */
    public function isRichText(): bool
    {
        return $this->richText;
    }

    /**
     * Rozhodne zda rich-text povoluje image upload
     */
    public function hasImageUpload(): bool
    {
        return $this->imageUpload;
    }

    /**
     * Rozhodne zda rich-text povoluje file upload
     */
    public function hasFileUpload(): bool
    {
        return $this->fileUpload;
    }

    /**
     * Nastavi placeholder a jeho volitelny prekladovy klic
     */
    public function placeholder(string $placeholder, ?string $key = null): self
    {
        return new self($this->common(), $this->input(), $placeholder, $key, $this->richText, $this->imageUpload, $this->fileUpload);
    }

    /**
     * Nastavi rich-text a jeho upload capabilities v jednom kroku
     */
    public function richText(bool $enabled = true, bool $imageUpload = false, bool $fileUpload = false): self
    {
        return new self(
            $this->common(),
            $this->input(),
            $this->placeholder,
            $this->placeholderKey,
            $enabled,
            $enabled && $imageUpload,
            $enabled && $fileUpload,
        );
    }

    /**
     * Nastavi image upload jiz aktivniho rich-textu
     */
    public function imageUpload(bool $enabled = true): self
    {
        if (!$this->richText) {
            throw new InvalidArgumentException('Only rich text textarea fields may enable image upload.');
        }

        return new self($this->common(), $this->input(), $this->placeholder, $this->placeholderKey, true, $enabled, $this->fileUpload);
    }

    /**
     * Nastavi file upload jiz aktivniho rich-textu
     */
    public function fileUpload(bool $enabled = true): self
    {
        if (!$this->richText) {
            throw new InvalidArgumentException('Only rich text textarea fields may enable file upload.');
        }

        return new self($this->common(), $this->input(), $this->placeholder, $this->placeholderKey, true, $this->imageUpload, $enabled);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->input(), $this->placeholder, $this->placeholderKey, $this->richText, $this->imageUpload, $this->fileUpload);
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self($this->common(), $input, $this->placeholder, $this->placeholderKey, $this->richText, $this->imageUpload, $this->fileUpload);
    }
}
