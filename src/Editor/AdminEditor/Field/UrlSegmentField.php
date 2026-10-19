<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use InvalidArgumentException;

/**
 * Definuje canonical URL segment a jeho samostatny action transport
 */
final readonly class UrlSegmentField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi URL metadata a action transport
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private ?string $prefix,
        private ?string $sourceField,
        private bool $autoGenerate,
        private ?string $actionKey,
        private ?string $actionLabel,
        private ?string $actionLabelKey,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori URL segment z factory facade
     */
    public static function create(string $name, string $label = '', ?string $labelKey = 'admin.url_segment.label'): self
    {
        return new self(AdminEditorFieldCommon::create($name, $label, $labelKey), new AdminEditorInputState(), null, null, false, null, null, null);
    }

    /**
     * Vrati needitovatelny prefix canonical cesty
     */
    public function pathPrefix(): ?string
    {
        return $this->prefix;
    }

    /**
     * Vrati zdrojove pole automatickeho segmentu
     */
    public function sourceField(): ?string
    {
        return $this->sourceField;
    }

    /**
     * Rozhodne zda prazdny segment sleduje zdrojove pole
     */
    public function autoGenerates(): bool
    {
        return $this->autoGenerate;
    }

    /**
     * Vrati klic samostatne URL akce
     */
    public function actionKey(): ?string
    {
        return $this->actionKey;
    }

    /**
     * Vrati zalozni text URL akce
     */
    public function actionText(): ?string
    {
        return $this->actionLabel;
    }

    /**
     * Vrati prekladovy klic URL akce
     */
    public function actionTranslationKey(): ?string
    {
        return $this->actionLabelKey;
    }

    /**
     * Nastavi needitovatelny prefix canonical cesty
     */
    public function prefix(?string $prefix): self
    {
        return new self($this->common(), $this->input(), $prefix, $this->sourceField, $this->autoGenerate, $this->actionKey, $this->actionLabel, $this->actionLabelKey);
    }

    /**
     * Nastavi zdrojove pole a automaticke generovani
     */
    public function source(string $field, bool $autoGenerate = false): self
    {
        if (trim($field) === '') {
            throw new InvalidArgumentException('URL segment source field must not be empty.');
        }

        return new self($this->common(), $this->input(), $this->prefix, $field, $autoGenerate, $this->actionKey, $this->actionLabel, $this->actionLabelKey);
    }

    /**
     * Nastavi samostatnou module action pro zmenu URL
     */
    public function action(
        string $key,
        string $label = '',
        ?string $labelKey = 'admin.url_segment.save',
    ): self {
        if (trim($key) === '') {
            throw new InvalidArgumentException('URL change action key must not be empty.');
        }
        if (trim($label) === '' && ($labelKey === null || trim($labelKey) === '')) {
            throw new InvalidArgumentException('URL change action label or label key must not be empty.');
        }

        return new self($this->common(), $this->input(), $this->prefix, $this->sourceField, $this->autoGenerate, $key, $label, $labelKey);
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self($common, $this->input(), $this->prefix, $this->sourceField, $this->autoGenerate, $this->actionKey, $this->actionLabel, $this->actionLabelKey);
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self($this->common(), $input, $this->prefix, $this->sourceField, $this->autoGenerate, $this->actionKey, $this->actionLabel, $this->actionLabelKey);
    }
}
