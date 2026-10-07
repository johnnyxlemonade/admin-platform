<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Popisuje nemennou konfiguraci admineditorfield
 */
final readonly class AdminEditorFieldDefinition implements AdminEditorBlock
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, scalar|bool|null> $attributes
     * @param array<string, scalar|bool|null> $wrapperAttributes
     * @param array<int|string, string> $options
     * @param array<int|string, string> $optionLabelKeys
     * @param list<string> $errors
     */
    private function __construct(
        private string $type,
        private string $name,
        private string $label = '',
        private ?string $labelKey = null,
        private mixed $value = null,
        private bool $hasValue = false,
        private mixed $defaultValue = null,
        private bool $hasDefaultValue = false,
        private ?string $help = null,
        private ?string $helpKey = null,
        private ?string $placeholder = null,
        private ?string $placeholderKey = null,
        private ?string $autocomplete = null,
        private bool $required = false,
        private bool $readonly = false,
        private bool $disabled = false,
        private array $errors = [],
        private array $attributes = [],
        private array $wrapperAttributes = [],
        private int $span = 1,
        private array $options = [],
        private array $optionLabelKeys = [],
        private ?string $displayValueKey = null,
        private bool $displayAsCode = false,
        private ?AdminEditorStatusVariant $displayStatusVariant = null,
        private bool $displayStatusIndicator = true,
        private ?string $unlockLabel = null,
        private ?string $unlockLabelKey = null,
        private ?self $markerField = null,
        private bool $multiple = false,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Field name must not be empty.');
        }
        if (!in_array($type, ['text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox', 'toggle', 'hidden', 'readonly_display', 'status_display', 'readonly_editable'], true)) {
            throw new InvalidArgumentException('Unsupported AdminEditor field type.');
        }
        if ($span < 1) {
            throw new InvalidArgumentException('Field span must be positive.');
        }
        if ($autocomplete !== null && trim($autocomplete) === '') {
            throw new InvalidArgumentException('Autocomplete value must not be empty.');
        }
    }

    /**
     * Vraci text nebo jeho lokalizovanou variantu
     */
    public static function text(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('text', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu email v konfiguraci editoru
     */
    public static function email(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('email', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu tel v konfiguraci editoru
     */
    public static function tel(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('tel', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu textarea v konfiguraci editoru
     */
    public static function textarea(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('textarea', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu number v konfiguraci editoru
     */
    public static function number(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('number', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu select v konfiguraci editoru
     */
    public static function select(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('select', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu checkbox v konfiguraci editoru
     */
    public static function checkbox(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('checkbox', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu toggle v konfiguraci editoru
     */
    public static function toggle(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('toggle', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu hidden v konfiguraci editoru
     */
    public static function hidden(string $name): self
    {
        return new self('hidden', $name);
    }

    /**
     * Zpracovava hodnotu readonlydisplay v konfiguraci editoru
     */
    public static function readonlyDisplay(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('readonly_display', $name, $label, $labelKey);
    }

    /**
     * Zpracovava hodnotu statusdisplay v konfiguraci editoru
     */
    public static function statusDisplay(string $name, string $label = '', ?string $labelKey = null, AdminEditorStatusVariant $variant = AdminEditorStatusVariant::Muted): self
    {
        return new self('status_display', $name, $label, $labelKey, displayStatusVariant: $variant);
    }

    /**
     * Zpracovava hodnotu readonlyeditable v konfiguraci editoru
     */
    public static function readonlyEditable(string $name, string $label = '', ?string $labelKey = null): self
    {
        return new self('readonly_editable', $name, $label, $labelKey, readonly: true);
    }

    /**
     * Zpracovava hodnotu type v konfiguraci editoru
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Zpracovava hodnotu name v konfiguraci editoru
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Zpracovava hodnotu label v konfiguraci editoru
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Zpracovava hodnotu labelkey v konfiguraci editoru
     */
    public function labelKey(): ?string
    {
        return $this->labelKey;
    }

    /**
     * Zpracovava hodnotu configuredvalue v konfiguraci editoru
     */
    public function configuredValue(): mixed
    {
        return $this->value;
    }

    /**
     * Rozhoduje stav hasvalue
     */
    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    /**
     * Zpracovava hodnotu configureddefaultvalue v konfiguraci editoru
     */
    public function configuredDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Rozhoduje stav hasdefaultvalue
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    /**
     * Zpracovava hodnotu helptext v konfiguraci editoru
     */
    public function helpText(): ?string
    {
        return $this->help;
    }

    /**
     * Zpracovava hodnotu helpkey v konfiguraci editoru
     */
    public function helpKey(): ?string
    {
        return $this->helpKey;
    }

    /**
     * Zpracovava hodnotu placeholdertext v konfiguraci editoru
     */
    public function placeholderText(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Zpracovava hodnotu placeholderkey v konfiguraci editoru
     */
    public function placeholderKey(): ?string
    {
        return $this->placeholderKey;
    }

    /**
     * Zpracovava hodnotu autocompletevalue v konfiguraci editoru
     */
    public function autocompleteValue(): ?string
    {
        return $this->autocomplete;
    }

    /**
     * Rozhoduje stav isrequired
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Rozhoduje stav isreadonly
     */
    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * Rozhoduje stav isdisabled
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Zpracovava hodnotu configurederrors v konfiguraci editoru
     * @return list<string>
     */
    public function configuredErrors(): array
    {
        return $this->errors;
    }

    /**
     * Zpracovava hodnotu inputattributes v konfiguraci editoru
     * @return array<string, scalar|bool|null>
     */
    public function inputAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Zpracovava hodnotu configuredwrapperattributes v konfiguraci editoru
     * @return array<string, scalar|bool|null>
     */
    public function configuredWrapperAttributes(): array
    {
        return $this->wrapperAttributes;
    }

    /**
     * Zpracovava hodnotu columnspan v konfiguraci editoru
     */
    public function columnSpan(): int
    {
        return $this->span;
    }

    /**
     * Zpracovava hodnotu selectoptions v konfiguraci editoru
     * @return array<int|string, string>
     */
    public function selectOptions(): array
    {
        return $this->options;
    }

    /**
     * Zpracovava hodnotu selectoptionlabelkeys v konfiguraci editoru
     * @return array<int|string, string>
     */
    public function selectOptionLabelKeys(): array
    {
        return $this->optionLabelKeys;
    }

    /**
     * Zpracovava hodnotu configureddisplayvaluekey v konfiguraci editoru
     */
    public function configuredDisplayValueKey(): ?string
    {
        return $this->displayValueKey;
    }

    /**
     * Zpracovava hodnotu displaysascode v konfiguraci editoru
     */
    public function displaysAsCode(): bool
    {
        return $this->displayAsCode;
    }

    /**
     * Zpracovava hodnotu displaystatusvariant v konfiguraci editoru
     */
    public function displayStatusVariant(): ?AdminEditorStatusVariant
    {
        return $this->displayStatusVariant;
    }

    /**
     * Zpracovava hodnotu displaysstatusindicator v konfiguraci editoru
     */
    public function displaysStatusIndicator(): bool
    {
        return $this->displayStatusIndicator;
    }

    /**
     * Zpracovava hodnotu unlocktext v konfiguraci editoru
     */
    public function unlockText(): ?string
    {
        return $this->unlockLabel;
    }

    /**
     * Zpracovava hodnotu unlocklabelkey v konfiguraci editoru
     */
    public function unlockLabelKey(): ?string
    {
        return $this->unlockLabelKey;
    }

    /**
     * Zpracovava hodnotu markerdefinition v konfiguraci editoru
     */
    public function markerDefinition(): ?self
    {
        return $this->markerField;
    }

    /**
     * Rozhoduje, zda select prijima vice hodnot
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Zpracovava hodnotu value v konfiguraci editoru
     */
    public function value(mixed $value): self
    {
        return $this->copy(value: $value, hasValue: true);
    }

    /**
     * Zpracovava hodnotu defaultvalue v konfiguraci editoru
     */
    public function defaultValue(mixed $value): self
    {
        return $this->copy(defaultValue: $value, hasDefaultValue: true);
    }

    /**
     * Zpracovava hodnotu help v konfiguraci editoru
     */
    public function help(string $value, ?string $key = null): self
    {
        return $this->copy(help: $value, helpKey: $key);
    }

    /**
     * Zpracovava hodnotu placeholder v konfiguraci editoru
     */
    public function placeholder(string $value, ?string $key = null): self
    {
        return $this->copy(placeholder: $value, placeholderKey: $key);
    }

    /**
     * Zpracovava hodnotu autocomplete v konfiguraci editoru
     */
    public function autocomplete(?string $value): self
    {
        return $this->copy(autocomplete: $value);
    }

    /**
     * Zpracovava hodnotu required v konfiguraci editoru
     */
    public function required(bool $value = true): self
    {
        return $this->copy(required: $value);
    }

    /**
     * Zpracovava hodnotu readonly v konfiguraci editoru
     */
    public function readonly(bool $value = true): self
    {
        return $this->copy(readonly: $value);
    }

    /**
     * Zpracovava hodnotu disabled v konfiguraci editoru
     */
    public function disabled(bool $value = true): self
    {
        return $this->copy(disabled: $value);
    }

    /**
     * Nastavuje select pro odeslani vice hodnot
     */
    public function multiple(bool $value = true): self
    {
        if ($this->type !== 'select') {
            throw new InvalidArgumentException('Only select fields may be multiple.');
        }

        return $this->copy(multiple: $value);
    }

    /**
     * Zpracovava hodnotu errors v konfiguraci editoru
     * @param list<string> $errors
     */
    public function errors(array $errors): self
    {
        return $this->copy(errors: $errors);
    }

    /**
     * Vytvari HTML atributy z povoleneho nastaveni
     * @param array<string, scalar|bool|null> $attributes
     */
    public function attributes(array $attributes): self
    {
        return $this->copy(attributes: $attributes);
    }

    /**
     * Zpracovava hodnotu wrapperattributes v konfiguraci editoru
     * @param array<string, scalar|bool|null> $attributes
     */
    public function wrapperAttributes(array $attributes): self
    {
        return $this->copy(wrapperAttributes: $attributes);
    }

    /**
     * Zpracovava hodnotu span v konfiguraci editoru
     */
    public function span(int $value): self
    {
        return $this->copy(span: $value);
    }

    /**
     * Zpracovava hodnotu options v konfiguraci editoru
     * @param array<int|string, string> $options
     */
    public function options(array $options): self
    {
        return $this->copy(options: $options);
    }

    /**
     * Zpracovava hodnotu optionlabelkeys v konfiguraci editoru
     * @param array<int|string, string> $keys
     */
    public function optionLabelKeys(array $keys): self
    {
        return $this->copy(optionLabelKeys: $keys);
    }

    /**
     * Zpracovava hodnotu displayvaluekey v konfiguraci editoru
     */
    public function displayValueKey(string $key): self
    {
        return $this->copy(displayValueKey: $key);
    }

    /**
     * Zpracovava hodnotu ascode v konfiguraci editoru
     */
    public function asCode(): self
    {
        return $this->copy(displayAsCode: true);
    }

    /**
     * Zpracovava hodnotu withoutstatusindicator v konfiguraci editoru
     */
    public function withoutStatusIndicator(): self
    {
        return $this->copy(displayStatusIndicator: false);
    }

    /**
     * Zpracovava hodnotu unlock v konfiguraci editoru
     */
    public function unlock(string $label, ?string $labelKey = null): self
    {
        return $this->copy(unlockLabel: $label, unlockLabelKey: $labelKey);
    }

    /**
     * Zpracovava hodnotu marker v konfiguraci editoru
     */
    public function marker(self $field): self
    {
        return $this->copy(markerField: $field);
    }

    /**
     * Vraci kopii definice s nahrazenymi predanymi hodnotami
     */
    private function copy(mixed ...$changes): self
    {
        return new self(
            $changes['type'] ?? $this->type,
            $changes['name'] ?? $this->name,
            $changes['label'] ?? $this->label,
            $changes['labelKey'] ?? $this->labelKey,
            $changes['value'] ?? $this->value,
            $changes['hasValue'] ?? $this->hasValue,
            $changes['defaultValue'] ?? $this->defaultValue,
            $changes['hasDefaultValue'] ?? $this->hasDefaultValue,
            $changes['help'] ?? $this->help,
            $changes['helpKey'] ?? $this->helpKey,
            $changes['placeholder'] ?? $this->placeholder,
            $changes['placeholderKey'] ?? $this->placeholderKey,
            $changes['autocomplete'] ?? $this->autocomplete,
            $changes['required'] ?? $this->required,
            $changes['readonly'] ?? $this->readonly,
            $changes['disabled'] ?? $this->disabled,
            $changes['errors'] ?? $this->errors,
            $changes['attributes'] ?? $this->attributes,
            $changes['wrapperAttributes'] ?? $this->wrapperAttributes,
            $changes['span'] ?? $this->span,
            $changes['options'] ?? $this->options,
            $changes['optionLabelKeys'] ?? $this->optionLabelKeys,
            $changes['displayValueKey'] ?? $this->displayValueKey,
            $changes['displayAsCode'] ?? $this->displayAsCode,
            $changes['displayStatusVariant'] ?? $this->displayStatusVariant,
            $changes['displayStatusIndicator'] ?? $this->displayStatusIndicator,
            $changes['unlockLabel'] ?? $this->unlockLabel,
            $changes['unlockLabelKey'] ?? $this->unlockLabelKey,
            $changes['markerField'] ?? $this->markerField,
            $changes['multiple'] ?? $this->multiple,
        );
    }
}
