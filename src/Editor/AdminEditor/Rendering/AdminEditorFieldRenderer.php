<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Rendering;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\ActionSelectField;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorInputFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\BooleanField;
use Lemonade\Admin\Editor\AdminEditor\Field\HiddenField;
use Lemonade\Admin\Editor\AdminEditor\Field\NativeInputType;
use Lemonade\Admin\Editor\AdminEditor\Field\ReadonlyDisplayField;
use Lemonade\Admin\Editor\AdminEditor\Field\ReadonlyEditableField;
use Lemonade\Admin\Editor\AdminEditor\Field\SelectField;
use Lemonade\Admin\Editor\AdminEditor\Field\StatusDisplayField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextareaField;
use Lemonade\Admin\Editor\AdminEditor\Field\TextInputField;
use Lemonade\Admin\Editor\AdminEditor\Field\UrlSegmentField;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Framework\View\ViewHelpers;
use LogicException;

/**
 * Vykresluje konkretni typovana pole AdminEditoru do stabilniho HTML kontraktu
 */
final readonly class AdminEditorFieldRenderer
{
    /**
     * Nastavi volitelne lokalizacni a upload zavislosti
     */
    public function __construct(
        private ?ViewHelpers $helpers = null,
        private ?AdminFileUploadComponent $files = null,
    ) {}

    /**
     * Vykresli pole podle jeho konkretniho typu a runtime kontextu
     */
    public function render(
        AdminEditorFieldDefinition $field,
        AdminEditorRenderContext $context,
        string $editorId,
        ?AdminActionFormDefinition $form = null,
    ): string {
        if ($field instanceof HiddenField) {
            return $this->hidden($field, $context, $editorId);
        }

        $inputId = $this->id($editorId . '-' . $field->name());
        $errorId = $inputId . '-error';
        $errors = $context->errorsFor($field->name());
        $help = $this->text($field->helpText(), $field->helpTranslationKey());
        $describedBy = [];
        if ($help !== null) {
            $describedBy[] = $inputId . '-help';
        }
        if ($errors !== []) {
            $describedBy[] = $errorId;
        }

        $html = '<div class="lm-form-group mb-4">';
        $label = $this->text($field->labelText(), $field->labelTranslationKey());
        if ($label !== null) {
            $required = $field instanceof AdminEditorInputFieldDefinition && $field->isRequired();
            $html .= '<label class="lm-form-label' . ($required ? ' required' : '') . '" for="' . $inputId . '"' . $this->translationAttribute($field->labelTranslationKey()) . '>' . $this->escape($label) . '</label>';
        }

        $html .= $this->control($field, $context, $editorId, $inputId, $describedBy, $errors, $form);
        if ($help !== null) {
            $html .= '<span id="' . $inputId . '-help" class="lm-form-help"' . $this->translationAttribute($field->helpTranslationKey()) . '>' . $this->escape($help) . '</span>';
        }
        if ($errors !== []) {
            $html .= '<span id="' . $errorId . '" class="lm-form-help is-error" data-lemonade-field-error="' . $this->escape($field->name()) . '">' . $this->escape($errors[0]) . '</span>';
        }

        return $html . '</div>';
    }

    /**
     * Dispatchuje rendering explicitne podle konkretni field family
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function control(
        AdminEditorFieldDefinition $field,
        AdminEditorRenderContext $context,
        string $editorId,
        string $inputId,
        array $describedBy,
        array $errors,
        ?AdminActionFormDefinition $form,
    ): string {
        $value = $this->value($field, $context);

        return match (true) {
            $field instanceof StatusDisplayField => $this->statusControl($field, $value, $inputId, $describedBy),
            $field instanceof ReadonlyDisplayField => $this->readonlyDisplayControl($field, $value, $inputId),
            $field instanceof BooleanField => $this->booleanControl($field, $value, $inputId, $describedBy, $errors),
            $field instanceof TextareaField => $this->textareaControl($field, $context, $value, $inputId, $describedBy, $errors),
            $field instanceof ActionSelectField => $this->actionSelectControl($field, $value, $inputId, $describedBy, $errors, $form),
            $field instanceof SelectField => $this->selectControl($field, $value, $inputId, $describedBy, $errors),
            $field instanceof TextInputField => $this->textInputControl($field, $value, $inputId, $describedBy, $errors),
            $field instanceof ReadonlyEditableField => $this->readonlyEditableControl($field, $context, $editorId, $value, $inputId, $describedBy, $errors),
            $field instanceof UrlSegmentField => $this->urlSegmentFieldControl($field, $value, $inputId, $describedBy, $errors, $form),
            default => throw new LogicException(sprintf('Unsupported AdminEditor field "%s".', $field::class)),
        };
    }

    /**
     * Vykresli boolean field jako checkbox nebo switch
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function booleanControl(BooleanField $field, mixed $value, string $inputId, array $describedBy, array $errors): string
    {
        $common = $this->inputCommon($field, $inputId, $describedBy, $errors);
        $checked = in_array((string) $value, ['1', 'true', 'on'], true) ? ' checked' : '';
        $preservedValue = $this->readonlyAsDisabled($field) && !$field->isDisabled()
            ? $this->preservedValue($field, $value)
            : '';

        return '<div class="form-check' . ($field->isToggle() ? ' form-switch' : '') . '"><input class="form-check-input" type="checkbox" value="1"' . $common . $checked . '></div>' . $preservedValue;
    }

    /**
     * Vykresli textarea vcetne rich-text upload atributu
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function textareaControl(
        TextareaField $field,
        AdminEditorRenderContext $context,
        mixed $value,
        string $inputId,
        array $describedBy,
        array $errors,
    ): string {
        $common = $this->inputCommon($field, $inputId, $describedBy, $errors);
        $richText = $field->isRichText() ? ' data-lemonade-admin-rich-text="jodit"' : '';

        return '<textarea class="form-control' . ($errors !== [] ? ' is-invalid' : '') . '"' . $common . $richText
            . $this->richTextImageUploadAttribute($field, $context)
            . $this->richTextFileUploadAttribute($field, $context)
            . $this->placeholder($field) . '>' . $this->escape($this->stringValue($value)) . '</textarea>';
    }

    /**
     * Vykresli single nebo multiple select
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function selectControl(SelectField $field, mixed $value, string $inputId, array $describedBy, array $errors): string
    {
        $common = $this->inputCommon($field, $inputId, $describedBy, $errors);
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

        $preservedValue = $this->readonlyAsDisabled($field) && !$field->isDisabled()
            ? $this->preservedValue($field, $value)
            : '';

        return $html . '</select>' . $preservedValue;
    }

    /**
     * Vykresli select a jeho samostatnou action
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function actionSelectControl(
        ActionSelectField $field,
        mixed $value,
        string $inputId,
        array $describedBy,
        array $errors,
        ?AdminActionFormDefinition $form,
    ): string {
        $attributes = $field->inputAttributes();
        $attributes['data-lemonade-transport-only'] = true;
        $common = $this->inputCommon($field, $inputId, $describedBy, $errors, attributes: $attributes);
        $html = '<div class="input-group lm-action-select"><select class="form-select' . ($errors !== [] ? ' is-invalid' : '') . '"' . $common . '>';
        foreach ($field->selectOptions() as $optionValue => $optionLabel) {
            $selected = (string) $value === (string) $optionValue;
            $html .= '<option value="' . $this->escape((string) $optionValue) . '"' . ($selected ? ' selected' : '') . '>' . $this->escape($optionLabel) . '</option>';
        }
        $html .= '</select>';
        $actionUrl = $form?->actionUrl();
        if ($form !== null && $actionUrl !== null) {
            $labelKey = $field->actionTranslationKey();
            $label = $this->text($field->actionText(), $labelKey);
            $html .= '<button class="btn btn-outline-secondary" type="button"'
                . ' data-lemonade-action data-lemonade-form="' . $this->escape($form->id()) . '"'
                . ' data-lemonade-url="' . $this->escape($actionUrl) . '"'
                . ' data-lemonade-method="' . $this->escape($form->method()) . '"'
                . ' data-lemonade-action-key="' . $this->escape($field->actionKey()) . '"'
                . ' data-lemonade-dirty-state-preserve'
                . $this->translationAttribute($labelKey) . '>' . $this->escape($label ?? '') . '</button>';
        }

        return $html . '</div>';
    }

    /**
     * Vykresli nativni textovy input
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function textInputControl(TextInputField $field, mixed $value, string $inputId, array $describedBy, array $errors): string
    {
        $inputValue = $field->inputType() === NativeInputType::DatetimeLocal
            ? $this->datetimeLocalValue($value)
            : $this->stringValue($value);
        $isDateTime = in_array($field->inputType(), [NativeInputType::Date, NativeInputType::DatetimeLocal], true);
        $inputType = $isDateTime ? NativeInputType::Text->value : $field->inputType()->value;
        $dateTime = $isDateTime ? ' data-lemonade-date-time="' . $field->inputType()->value . '"' : '';

        return '<input class="form-control' . ($errors !== [] ? ' is-invalid' : '') . '" type="' . $inputType . '" value="' . $this->escape($inputValue) . '"' . $dateTime
            . $this->inputCommon($field, $inputId, $describedBy, $errors, $field->autocompleteValue())
            . $this->placeholder($field) . '>';
    }

    /**
     * Vykresli odemykatelny readonly input a jeho marker
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function readonlyEditableControl(
        ReadonlyEditableField $field,
        AdminEditorRenderContext $context,
        string $editorId,
        mixed $value,
        string $inputId,
        array $describedBy,
        array $errors,
    ): string {
        $input = '<input class="form-control' . ($errors !== [] ? ' is-invalid' : '') . '" type="text" value="' . $this->escape($this->stringValue($value)) . '"'
            . $this->inputCommon($field, $inputId, $describedBy, $errors, $field->autocompleteValue())
            . $this->placeholder($field) . '>';
        $unlock = $this->text($field->unlockText(), $field->unlockKey()) ?? '';
        $marker = $field->markerField();
        $markerHtml = $marker === null ? '' : $this->hidden($marker, $context, $editorId);

        return '<div class="input-group" data-lemonade-editable-field-root>' . $input . '<button class="btn btn-outline-secondary" type="button" data-lemonade-editable-field-unlock data-lemonade-editable-field-target="' . $inputId . '"' . ($marker === null ? '' : ' data-lemonade-editable-field-marker="' . $this->id($editorId . '-' . $marker->name()) . '"') . $this->translationAttribute($field->unlockKey()) . '>' . $this->escape($unlock) . '</button></div>' . $markerHtml;
    }

    /**
     * Vykresli URL segment a jeho samostatnou action
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     */
    private function urlSegmentFieldControl(
        UrlSegmentField $field,
        mixed $value,
        string $inputId,
        array $describedBy,
        array $errors,
        ?AdminActionFormDefinition $form,
    ): string {
        $attributes = $field->inputAttributes();
        $attributes['data-lemonade-url-segment'] = true;
        $attributes['data-lemonade-transport-only'] = true;
        if ($field->sourceField() !== null) {
            $attributes['data-lemonade-url-segment-source'] = $field->sourceField();
        }
        if ($field->autoGenerates()) {
            $attributes['data-lemonade-url-segment-auto'] = true;
        }

        $input = '<input class="form-control' . ($errors !== [] ? ' is-invalid' : '') . '" type="text" value="' . $this->escape($this->stringValue($value)) . '"'
            . $this->inputCommon($field, $inputId, $describedBy, $errors, attributes: $attributes) . '>';
        $html = '<div class="input-group" data-lemonade-url-segment-root>';
        $prefix = $field->pathPrefix();
        if ($prefix !== null && $prefix !== '') {
            $html .= '<span class="input-group-text" data-lemonade-url-segment-prefix>' . $this->escape($prefix) . '</span>';
        }
        $html .= $input;
        $actionUrl = $form?->actionUrl();
        $actionKey = $field->actionKey();
        if ($form !== null && $actionUrl !== null && $actionKey !== null) {
            $labelKey = $field->actionTranslationKey();
            $label = $this->text($field->actionText() ?? '', $labelKey);
            $html .= '<button class="btn btn-outline-secondary" type="button"'
                . ' data-lemonade-action data-lemonade-form="' . $this->escape($form->id()) . '"'
                . ' data-lemonade-url="' . $this->escape($actionUrl) . '"'
                . ' data-lemonade-method="' . $this->escape($form->method()) . '"'
                . ' data-lemonade-action-key="' . $this->escape($actionKey) . '"'
                . ' data-lemonade-url-segment-submit data-lemonade-dirty-state-preserve'
                . $this->translationAttribute($labelKey) . '>' . $this->escape($label ?? '') . '</button>';
        }

        return $html . '</div>';
    }

    /**
     * Vykresli readonly textovy vystup
     */
    private function readonlyDisplayControl(ReadonlyDisplayField $field, mixed $value, string $inputId): string
    {
        $displayValue = $this->displayValue($field->displayTranslationKey(), $value);
        $content = $this->escape($displayValue);
        if ($field->displaysAsCode()) {
            $content = '<code>' . $content . '</code>';
        }

        return '<p id="' . $inputId . '" class="form-control-plaintext mb-0"' . $this->translationAttribute($field->displayTranslationKey()) . '>' . $content . '</p>';
    }

    /**
     * Vykresli status vystup
     *
     * @param list<string> $describedBy
     */
    private function statusControl(StatusDisplayField $field, mixed $value, string $inputId, array $describedBy): string
    {
        $indicator = $field->displaysIndicator()
            ? '<span class="admin-editor-status-display-indicator" aria-hidden="true"></span>'
            : '';
        $describedByAttribute = $describedBy === [] ? '' : ' aria-describedby="' . implode(' ', $describedBy) . '"';
        $displayValue = $this->displayValue($field->displayTranslationKey(), $value);

        return '<div id="' . $inputId . '" class="admin-editor-status-display admin-editor-status-display--' . $this->escape($field->variant()->value) . '" role="status"' . $describedByAttribute . '>' . $indicator . '<span' . $this->translationAttribute($field->displayTranslationKey()) . '>' . $this->escape($displayValue) . '</span></div>';
    }

    /**
     * Vykresli skryty transportni field
     */
    private function hidden(HiddenField $field, AdminEditorRenderContext $context, string $editorId): string
    {
        return '<input id="' . $this->id($editorId . '-' . $field->name()) . '" name="' . $this->escape($field->name()) . '" type="hidden" value="' . $this->escape($this->stringValue($this->value($field, $context))) . '" data-lemonade-field="' . $this->escape($field->name()) . '"' . $this->attributes($field->inputAttributes()) . '>';
    }

    /**
     * Sestavi atributy sdilene formularovymi vstupy
     *
     * @param list<string> $describedBy
     * @param list<string> $errors
     * @param array<string, scalar|bool|null>|null $attributes
     */
    private function inputCommon(
        AdminEditorInputFieldDefinition $field,
        string $inputId,
        array $describedBy,
        array $errors,
        ?string $autocomplete = null,
        ?array $attributes = null,
    ): string {
        $readonlyAsDisabled = $this->readonlyAsDisabled($field);
        $inputAttributes = $attributes ?? $field->inputAttributes();
        if ($autocomplete !== null) {
            $inputAttributes['autocomplete'] = $autocomplete;
        }
        $fieldName = $field instanceof SelectField && $field->isMultiple()
            ? $field->name() . '[]'
            : $field->name();

        return ' id="' . $inputId . '" name="' . $this->escape($fieldName) . '" data-lemonade-field="' . $this->escape($field->name()) . '"'
            . ($field->isRequired() && !$field->isReadonly() ? ' required' : '')
            . ($field->isReadonly() && !$readonlyAsDisabled ? ' readonly' : '')
            . ($field->isDisabled() || $readonlyAsDisabled ? ' disabled' : '')
            . ($errors !== [] ? ' aria-invalid="true"' : '')
            . ($describedBy !== [] ? ' aria-describedby="' . implode(' ', $describedBy) . '"' : '')
            . $this->attributes($inputAttributes);
    }

    /**
     * Rozhodne zda readonly field pouziva disabled HTML control
     */
    private function readonlyAsDisabled(AdminEditorInputFieldDefinition $field): bool
    {
        return $field->isReadonly() && ($field instanceof BooleanField || $field instanceof SelectField);
    }

    /**
     * Vykresli hidden hodnotu zachovavajici readonly disabled control
     */
    private function preservedValue(BooleanField|SelectField $field, mixed $value): string
    {
        if ($field instanceof BooleanField) {
            $value = in_array((string) $value, ['1', 'true', 'on'], true) ? '1' : '0';
        }

        return '<input name="' . $this->escape($field->name()) . '" type="hidden" value="' . $this->escape($this->stringValue($value)) . '" data-lemonade-field="' . $this->escape($field->name()) . '">';
    }

    /**
     * Vybere runtime hodnotu podle canonical precedence
     */
    private function value(AdminEditorFieldDefinition $field, AdminEditorRenderContext $context): mixed
    {
        if (array_key_exists($field->name(), $context->oldInput())) {
            return $context->oldInput()[$field->name()];
        }
        if (array_key_exists($field->name(), $context->values())) {
            return $context->values()[$field->name()];
        }

        return $field->hasDefaultValue() ? $field->structuralDefaultValue() : null;
    }

    /**
     * Sestavi rich-text image upload target z runtime kontextu
     */
    private function richTextImageUploadAttribute(TextareaField $field, AdminEditorRenderContext $context): string
    {
        if (!$field->hasImageUpload() || $context->moduleCode() === null || $context->entityId() === null) {
            return '';
        }
        if ($this->files === null) {
            throw new LogicException('Rich-text image upload requires AdminFileUploadComponent.');
        }

        $target = $this->files->richTextImageTarget($context->moduleCode(), $context->entityId());

        return ' data-lemonade-rich-text-upload-target="' . $this->escape($target->toJson()) . '"';
    }

    /**
     * Sestavi rich-text file upload target z runtime kontextu
     */
    private function richTextFileUploadAttribute(TextareaField $field, AdminEditorRenderContext $context): string
    {
        if (!$field->hasFileUpload() || $context->moduleCode() === null || $context->entityId() === null) {
            return '';
        }
        if ($this->files === null) {
            throw new LogicException('Rich-text file upload requires AdminFileUploadComponent.');
        }

        $target = $this->files->richTextFileTarget($context->moduleCode(), $context->entityId());

        return ' data-lemonade-rich-text-file-upload-target="' . $this->escape($target->toJson()) . '"';
    }

    /**
     * Normalizuje multiple hodnotu na seznam stringu
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
     * Vrati text nebo jeho lokalizovanou variantu
     */
    private function text(?string $value, ?string $key): ?string
    {
        if ($key !== null && $this->helpers !== null) {
            return $this->helpers->lang($key);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Vrati lokalizovanou nebo runtime display hodnotu
     */
    private function displayValue(?string $key, mixed $value): string
    {
        if ($key !== null && $this->helpers !== null) {
            return $this->helpers->lang($key);
        }

        return $this->stringValue($value);
    }

    /**
     * Vykresli placeholder podporovaneho textoveho controlu
     */
    private function placeholder(TextInputField|TextareaField|ReadonlyEditableField $field): string
    {
        $value = $this->text($field->placeholderText(), $field->placeholderKey());

        return $value === null ? '' : ' placeholder="' . $this->escape($value) . '"' . $this->translationAttribute($field->placeholderKey(), 'data-lemonade-i18n-placeholder');
    }

    /**
     * Vykresli prekladovy data atribut
     */
    private function translationAttribute(?string $key, string $attribute = 'data-lemonade-i18n'): string
    {
        return $key === null ? '' : ' ' . $attribute . '="' . $this->escape($key) . '"';
    }

    /**
     * Vykresli povolene HTML atributy
     *
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
     * Normalizuje hodnotu pro HTML id
     */
    private function id(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? $value;
    }

    /**
     * Prevede scalarni nebo Stringable hodnotu na string
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    /**
     * Prevede canonical databazovy datetime na native HTML hodnotu
     */
    private function datetimeLocalValue(mixed $value): string
    {
        $datetime = $this->stringValue($value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $datetime, $parts) !== 1) {
            return $datetime;
        }

        return $parts[1] . 'T' . $parts[2];
    }

    /**
     * Escapuje text pro bezpecne vlozeni do HTML
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
