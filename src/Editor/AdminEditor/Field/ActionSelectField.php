<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

use InvalidArgumentException;

/**
 * Definuje single select s explicitni samostatnou module action
 */
final readonly class ActionSelectField extends AdminEditorInputFieldDefinition
{
    /**
     * Nastavi options a samostatny action transport
     *
     * @param array<int|string, string> $options
     */
    private function __construct(
        AdminEditorFieldCommon $common,
        AdminEditorInputState $input,
        private array $options,
        private string $actionKey,
        private string $actionLabel,
        private ?string $actionLabelKey,
    ) {
        parent::__construct($common, $input);
    }

    /**
     * Vytvori action select z factory facade
     */
    public static function create(
        string $name,
        string $actionKey,
        string $label = '',
        ?string $labelKey = null,
        string $actionLabel = '',
        ?string $actionLabelKey = null,
    ): self {
        if (trim($actionKey) === '') {
            throw new InvalidArgumentException('Action select key must not be empty.');
        }
        if (trim($actionLabel) === '' && ($actionLabelKey === null || trim($actionLabelKey) === '')) {
            throw new InvalidArgumentException('Action select label or label key must not be empty.');
        }

        return new self(
            AdminEditorFieldCommon::create($name, $label, $labelKey),
            new AdminEditorInputState(),
            [],
            $actionKey,
            $actionLabel,
            $actionLabelKey,
        );
    }

    /**
     * Vrati select options
     *
     * @return array<int|string, string>
     */
    public function selectOptions(): array
    {
        return $this->options;
    }

    /**
     * Vrati klic samostatne akce
     */
    public function actionKey(): string
    {
        return $this->actionKey;
    }

    /**
     * Vrati zalozni text akce
     */
    public function actionText(): string
    {
        return $this->actionLabel;
    }

    /**
     * Vrati prekladovy klic akce
     */
    public function actionTranslationKey(): ?string
    {
        return $this->actionLabelKey;
    }

    /**
     * Nastavi select options
     *
     * @param array<int|string, string> $options
     */
    public function options(array $options): self
    {
        return new self(
            $this->common(),
            $this->input(),
            $options,
            $this->actionKey,
            $this->actionLabel,
            $this->actionLabelKey,
        );
    }

    /**
     * Vytvori field s novymi spolecnymi daty
     */
    protected function withCommon(AdminEditorFieldCommon $common): static
    {
        return new self(
            $common,
            $this->input(),
            $this->options,
            $this->actionKey,
            $this->actionLabel,
            $this->actionLabelKey,
        );
    }

    /**
     * Vytvori field s novym vstupnim stavem
     */
    protected function withInputState(AdminEditorInputState $input): static
    {
        return new self(
            $this->common(),
            $input,
            $this->options,
            $this->actionKey,
            $this->actionLabel,
            $this->actionLabelKey,
        );
    }
}
