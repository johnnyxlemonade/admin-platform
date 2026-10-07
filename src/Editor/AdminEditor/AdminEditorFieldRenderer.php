<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use Lemonade\Framework\View\ViewHelpers;

/**
 * Vykresluje cast administracniho editoru do HTML
 */
final class AdminEditorFieldRenderer
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(private readonly ?ViewHelpers $helpers = null) {}

    /**
     * Vykresluje editor do HTML podle predane definice a kontextu
     */
    public function render(AdminEditorFieldDefinition $field, AdminEditorRenderContext $context, string $editorId): string
    {
        if ($field->type() === 'hidden') {
            return $this->hidden($field, $context, $editorId);
        }

        $inputId = $this->id($editorId . '-' . $field->name());
        $errorId = $inputId . '-error';
        $errors = $context->errorsFor($field->name());
        if ($errors === []) {
            $errors = $field->configuredErrors();
        }
        $help = $this->text($field->helpText(), $field->helpKey());
        $describedBy = [];
        if ($help !== null) {
            $describedBy[] = $inputId . '-help';
        }
        if ($errors !== []) {
            $describedBy[] = $errorId;
        }
        $wrapper = 'lm-form-group mb-4' . ($field->columnSpan() > 1 ? ' admin-editor-span-' . $field->columnSpan() : '');
        $html = '<div class="' . $wrapper . '"' . $this->attributes($field->configuredWrapperAttributes()) . '>';
        $label = $this->text($field->label(), $field->labelKey());
        if ($label !== null) {
            $html .= '<label class="lm-form-label' . ($field->isRequired() ? ' required' : '') . '" for="' . $inputId . '"' . $this->translationAttribute($field->labelKey()) . '>' . $this->escape($label) . '</label>';
        }
        $html .= $this->control($field, $context, $editorId, $inputId, $describedBy, $errors);
        if ($help !== null) {
            $html .= '<span id="' . $inputId . '-help" class="lm-form-help"' . $this->translationAttribute($field->helpKey()) . '>' . $this->escape($help) . '</span>';
        }
        if ($errors !== []) {
            $html .= '<span id="' . $errorId . '" class="lm-form-help is-error" data-lemonade-field-error="' . $this->escape($field->name()) . '">' . $this->escape($errors[0]) . '</span>';
        }

        return $html . '</div>';
    }

    /**
     * Zpracovava hodnotu control v konfiguraci editoru
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function control(AdminEditorFieldDefinition $field, AdminEditorRenderContext $context, string $editorId, string $inputId, array $describedBy, array $errors): string
    {
        $value = $this->value($field, $context);
        $readonlyAsDisabled = $field->isReadonly() && in_array($field->type(), ['checkbox', 'toggle', 'select'], true);
        $inputAttributes = $field->inputAttributes();
        if ($field->autocompleteValue() !== null) {
            $inputAttributes['autocomplete'] = $field->autocompleteValue();
        }
        if ($field->type() === 'status_display') {
            return $this->statusControl($field, $value, $inputId, $describedBy);
        }
        if ($field->type() === 'readonly_display') {
            $displayValue = $this->displayValue($field, $value);
            $content = $this->escape($displayValue);
            if ($field->displaysAsCode()) {
                $content = '<code>' . $content . '</code>';
            }
            return '<p id="' . $inputId . '" class="form-control-plaintext mb-0"' . $this->translationAttribute($field->configuredDisplayValueKey()) . '>' . $content . '</p>';
        }

        $fieldName = $field->isMultiple() ? $field->name() . '[]' : $field->name();
        $common = ' id="' . $inputId . '" name="' . $this->escape($fieldName) . '" data-lemonade-field="' . $this->escape($field->name()) . '"'
            . ($field->isRequired() && !$field->isReadonly() ? ' required' : '') . ($field->isReadonly() && !$readonlyAsDisabled ? ' readonly' : '') . ($field->isDisabled() || $readonlyAsDisabled ? ' disabled' : '')
            . ($errors !== [] ? ' aria-invalid="true"' : '') . ($describedBy !== [] ? ' aria-describedby="' . implode(' ', $describedBy) . '"' : '') . $this->attributes($inputAttributes);
        $preservedValue = $readonlyAsDisabled && !$field->isDisabled() ? $this->preservedValue($field, $value) : '';

        if (in_array($field->type(), ['checkbox', 'toggle'], true)) {
            $checked = in_array((string) $value, ['1', 'true', 'on'], true) ? ' checked' : '';
            $class = $field->type() === 'toggle' ? 'form-check-input' : 'form-check-input';
            return '<div class="form-check' . ($field->type() === 'toggle' ? ' form-switch' : '') . '"><input class="' . $class . '" type="checkbox" value="1"' . $common . $checked . '></div>' . $preservedValue;
        }
        if ($field->type() === 'textarea') {
            return '<textarea class="form-control' . ($errors !== [] ? ' is-invalid' : '') . '"' . $common . $this->placeholder($field) . '>' . $this->escape($this->stringValue($value)) . '</textarea>';
        }
        if ($field->type() === 'select') {
            $selectedValues = $field->isMultiple() ? $this->multipleValues($value) : [];
            $html = '<select class="form-select' . ($errors !== [] ? ' is-invalid' : '') . '"' . $common . ($field->isMultiple() ? ' multiple' : '') . '>';
            foreach ($field->selectOptions() as $optionValue => $optionLabel) {
                $optionLabelKey = $field->selectOptionLabelKeys()[$optionValue] ?? null;
                $label = $this->text($optionLabel, $optionLabelKey) ?? '';
                $selected = $field->isMultiple()
                    ? in_array((string) $optionValue, $selectedValues, true)
                    : (string) $value === (string) $optionValue;
                $html .= '<option value="' . $this->escape((string) $optionValue) . '"' . ($selected ? ' selected' : '') . $this->translationAttribute($optionLabelKey) . '>' . $this->escape($label) . '</option>';
            }
            return $html . '</select>' . $preservedValue;
        }

        $inputType = match ($field->type()) {
            'email' => 'email',
            'number' => 'number',
            'tel' => 'tel',
            default => 'text',
        };
        $input = '<input class="form-control' . ($errors !== [] ? ' is-invalid' : '') . '" type="' . $inputType . '" value="' . $this->escape($this->stringValue($value)) . '"' . $common . $this->placeholder($field) . '>';
        if ($field->type() !== 'readonly_editable') {
            return $input;
        }

        $unlock = $this->text($field->unlockText(), $field->unlockLabelKey()) ?? '';
        $marker = $field->markerDefinition();
        $markerHtml = $marker === null ? '' : $this->hidden($marker, $context, $editorId);
        return '<div class="input-group" data-lemonade-editable-field-root>' . $input . '<button class="btn btn-outline-secondary" type="button" data-lemonade-editable-field-unlock data-lemonade-editable-field-target="' . $inputId . '"' . ($marker === null ? '' : ' data-lemonade-editable-field-marker="' . $this->id($editorId . '-' . $marker->name()) . '"') . $this->translationAttribute($field->unlockLabelKey()) . '>' . $this->escape($unlock) . '</button></div>' . $markerHtml;
    }

    /**
     * Zpracovava hodnotu statuscontrol v konfiguraci editoru
     * @param list<string> $describedBy
     */
    private function statusControl(AdminEditorFieldDefinition $field, mixed $value, string $inputId, array $describedBy): string
    {
        $variant = $field->displayStatusVariant() ?? AdminEditorStatusVariant::Muted;
        $indicator = $field->displaysStatusIndicator()
            ? '<span class="admin-editor-status-display-indicator" aria-hidden="true"></span>'
            : '';
        $describedByAttribute = $describedBy === [] ? '' : ' aria-describedby="' . implode(' ', $describedBy) . '"';
        $displayValue = $this->displayValue($field, $value);

        return '<div id="' . $inputId . '" class="admin-editor-status-display admin-editor-status-display--' . $this->escape($variant->value) . '" role="status"' . $describedByAttribute . '>' . $indicator . '<span' . $this->translationAttribute($field->configuredDisplayValueKey()) . '>' . $this->escape($displayValue) . '</span></div>';
    }

    /**
     * Zpracovava hodnotu hidden v konfiguraci editoru
     */
    private function hidden(AdminEditorFieldDefinition $field, AdminEditorRenderContext $context, string $editorId): string
    {
        return '<input id="' . $this->id($editorId . '-' . $field->name()) . '" name="' . $this->escape($field->name()) . '" type="hidden" value="' . $this->escape($this->stringValue($this->value($field, $context))) . '" data-lemonade-field="' . $this->escape($field->name()) . '"' . $this->attributes($field->inputAttributes()) . '>';
    }

    /**
     * Zpracovava hodnotu preservedvalue v konfiguraci editoru
     */
    private function preservedValue(AdminEditorFieldDefinition $field, mixed $value): string
    {
        if (in_array($field->type(), ['checkbox', 'toggle'], true)) {
            $value = in_array((string) $value, ['1', 'true', 'on'], true) ? '1' : '0';
        }
        return '<input name="' . $this->escape($field->name()) . '" type="hidden" value="' . $this->escape($this->stringValue($value)) . '" data-lemonade-field="' . $this->escape($field->name()) . '">';
    }

    /**
     * Zpracovava hodnotu value v konfiguraci editoru
     */
    private function value(AdminEditorFieldDefinition $field, AdminEditorRenderContext $context): mixed
    {
        if (array_key_exists($field->name(), $context->oldInput())) {
            return $context->oldInput()[$field->name()];
        }
        if ($field->hasValue()) {
            return $field->configuredValue();
        }
        if (array_key_exists($field->name(), $context->values())) {
            return $context->values()[$field->name()];
        }
        return $field->hasDefaultValue() ? $field->configuredDefaultValue() : null;
    }

    /**
     * Normalizuje hodnotu multiple selectu na seznam odesilanych stringu
     *
     * @return list<string>
     */
    private function multipleValues(mixed $value): array
    {
        if (!is_array($value)) {
            return $value === null ? [] : [(string) $value];
        }

        return array_values(array_map(static fn(mixed $item): string => (string) $item, $value));
    }

    /**
     * Vraci text nebo jeho lokalizovanou variantu
     */
    private function text(?string $value, ?string $key): ?string
    {
        if ($key !== null && $this->helpers !== null) {
            return $this->helpers->lang($key);
        }
        return $value === '' ? null : $value;
    }

    /**
     * Zpracovava hodnotu displayvalue v konfiguraci editoru
     */
    private function displayValue(AdminEditorFieldDefinition $field, mixed $value): string
    {
        $key = $field->configuredDisplayValueKey();
        if ($key !== null && $this->helpers !== null) {
            return $this->helpers->lang($key);
        }
        return $this->stringValue($value);
    }

    /**
     * Zpracovava hodnotu placeholder v konfiguraci editoru
     */
    private function placeholder(AdminEditorFieldDefinition $field): string
    {
        $value = $this->text($field->placeholderText(), $field->placeholderKey());
        return $value === null ? '' : ' placeholder="' . $this->escape($value) . '"' . $this->translationAttribute($field->placeholderKey(), 'data-lemonade-i18n-placeholder');
    }

    /**
     * Zpracovava hodnotu translationattribute v konfiguraci editoru
     */
    private function translationAttribute(?string $key, string $attribute = 'data-lemonade-i18n'): string
    {
        return $key === null ? '' : ' ' . $attribute . '="' . $this->escape($key) . '"';
    }

    /**
     * Vytvari HTML atributy z povoleneho nastaveni
     * @param array<string, scalar|bool|null> $attributes
     */
    private function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $html .= ' ' . $this->escape($key) . ($value === true ? '' : '="' . $this->escape((string) $value) . '"');
        }
        return $html;
    }

    /**
     * Zpracovava hodnotu id v konfiguraci editoru
     */
    private function id(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? $value;
    }

    /**
     * Zpracovava hodnotu stringvalue v konfiguraci editoru
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    /**
     * Escapuje text pro bezpecne vlozeni do HTML
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
