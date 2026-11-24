<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use Lemonade\Admin\Select\StaticSelectOptionSource;

/**
 * Defines accepted values and presentation for a DataGrid filter
 */
final readonly class DataGridFilterDefinition
{
    /**
     * Creates a filter with static options or validated asynchronous values
     */
    public function __construct(
        private string $key,
        private StaticSelectOptionSource $optionSource,
        private ?string $label = null,
        private ?string $allLabelKey = null,
        private ?string $inputType = null,
        private ?string $valuePattern = null,
        private ?string $asyncUrl = null,
    ) {}

    /**
     * Accepts a static option or a value matching the configured pattern
     */
    public function accepts(string $value): bool
    {
        if ($this->inputType === null && $this->asyncUrl === null) {
            return in_array($value, $this->allowedValues(), true);
        }
        return $this->valuePattern !== null && strlen($value) <= 255 && preg_match($this->valuePattern, $value) === 1;
    }

    /**
     * Returns the filter key
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Returns the values of statically configured options
     *
     * @return list<string>
     */
    public function allowedValues(): array
    {
        return $this->optionSource->values();
    }

    /**
     * Returns the static option source
     */
    public function optionSource(): StaticSelectOptionSource
    {
        return $this->optionSource;
    }

    /**
     * Returns static options for the client transport
     *
     * @return list<array{value:string,label:string}>
     */
    public function options(): array
    {
        return array_map(static fn(\Lemonade\Admin\Select\SelectOptionDefinition $option): array => $option->toArray(), $this->optionSource->options());
    }

    /**
     * Returns the optional untranslated filter label
     */
    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * Returns the translation key for the empty option
     */
    public function allLabelKey(): ?string
    {
        return $this->allLabelKey;
    }

    /**
     * Returns the input type for a scalar filter
     */
    public function inputType(): ?string
    {
        return $this->inputType;
    }

    /**
     * Returns the endpoint for asynchronously loaded options
     */
    public function asyncUrl(): ?string
    {
        return $this->asyncUrl;
    }
}
