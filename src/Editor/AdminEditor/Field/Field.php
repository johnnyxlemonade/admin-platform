<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Poskytuje stateless factory vstupni bod pro konkretni editorova pole
 */
final class Field
{
    /**
     * Brani vytvareni instanci stateless factory facade
     */
    private function __construct() {}

    /**
     * Vytvori jednoradkovy textovy vstup
     */
    public static function text(string $name, string $label = '', ?string $labelKey = null): TextInputField
    {
        return TextInputField::create($name, NativeInputType::Text, $label, $labelKey);
    }

    /**
     * Vytvori emailovy vstup
     */
    public static function email(string $name, string $label = '', ?string $labelKey = null): TextInputField
    {
        return TextInputField::create($name, NativeInputType::Email, $label, $labelKey);
    }

    /**
     * Vytvori telefonni vstup
     */
    public static function tel(string $name, string $label = '', ?string $labelKey = null): TextInputField
    {
        return TextInputField::create($name, NativeInputType::Tel, $label, $labelKey);
    }

    /**
     * Vytvori ciselny vstup
     */
    public static function number(string $name, string $label = '', ?string $labelKey = null): TextInputField
    {
        return TextInputField::create($name, NativeInputType::Number, $label, $labelKey);
    }

    /**
     * Vytvori vstup data
     */
    public static function date(string $name, string $label = '', ?string $labelKey = null): TextInputField
    {
        return TextInputField::create($name, NativeInputType::Date, $label, $labelKey);
    }

    /**
     * Vytvori vstup lokalniho data a casu
     */
    public static function datetimeLocal(string $name, string $label = '', ?string $labelKey = null): TextInputField
    {
        return TextInputField::create($name, NativeInputType::DatetimeLocal, $label, $labelKey);
    }

    /**
     * Vytvori vice radkovy textovy vstup
     */
    public static function textarea(string $name, string $label = '', ?string $labelKey = null): TextareaField
    {
        return TextareaField::create($name, $label, $labelKey);
    }

    /**
     * Vytvori select vstup
     */
    public static function select(string $name, string $label = '', ?string $labelKey = null): SelectField
    {
        return SelectField::create($name, $label, $labelKey);
    }

    /**
     * Vytvori single select se samostatnou module action
     */
    public static function actionSelect(
        string $name,
        string $actionKey,
        string $label = '',
        ?string $labelKey = null,
        string $actionLabel = '',
        ?string $actionLabelKey = null,
    ): ActionSelectField {
        return ActionSelectField::create(
            $name,
            $actionKey,
            $label,
            $labelKey,
            $actionLabel,
            $actionLabelKey,
        );
    }

    /**
     * Vytvori checkbox vstup
     */
    public static function checkbox(string $name, string $label = '', ?string $labelKey = null): BooleanField
    {
        return BooleanField::create($name, false, $label, $labelKey);
    }

    /**
     * Vytvori toggle vstup
     */
    public static function toggle(string $name, string $label = '', ?string $labelKey = null): BooleanField
    {
        return BooleanField::create($name, true, $label, $labelKey);
    }

    /**
     * Vytvori skryty transportni vstup
     */
    public static function hidden(string $name): HiddenField
    {
        return HiddenField::create($name);
    }

    /**
     * Vytvori needitovatelny textovy vystup
     */
    public static function readonlyDisplay(string $name, string $label = '', ?string $labelKey = null): ReadonlyDisplayField
    {
        return ReadonlyDisplayField::create($name, $label, $labelKey);
    }

    /**
     * Vytvori stavovy vystup
     */
    public static function statusDisplay(
        string $name,
        string $label = '',
        ?string $labelKey = null,
        AdminEditorStatusVariant $variant = AdminEditorStatusVariant::Muted,
    ): StatusDisplayField {
        return StatusDisplayField::create($name, $label, $labelKey, $variant);
    }

    /**
     * Vytvori explicitne odemykatelny readonly vstup
     */
    public static function readonlyEditable(string $name, string $label = '', ?string $labelKey = null): ReadonlyEditableField
    {
        return ReadonlyEditableField::create($name, $label, $labelKey);
    }

    /**
     * Vytvori URL segment s oddelenym action transportem
     */
    public static function urlSegment(
        string $name,
        string $label = '',
        ?string $labelKey = 'admin.url_segment.label',
    ): UrlSegmentField {
        return UrlSegmentField::create($name, $label, $labelKey)
            ->help('', 'admin.url_segment.help');
    }
}
