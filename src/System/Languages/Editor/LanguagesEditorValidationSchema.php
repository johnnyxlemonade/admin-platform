<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Editor;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Sklada vstupni validaci create a update editoru jazyku
 */
final class LanguagesEditorValidationSchema
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * Vyzaduje code pouze pri vytvareni noveho jazyka
     */
    public function forCreate(): ValidationSchema
    {
        return $this->schema(true);
    }

    /**
     * Validuje jen udaje, ktere update muze zmenit
     */
    public function forUpdate(): ValidationSchema
    {
        return $this->schema(false);
    }

    /**
     * Pridava code pravidla jen do create schematu
     */
    private function schema(bool $create): ValidationSchema
    {
        $schema = ValidationSchema::create();
        if ($create) {
            $schema->field('code', $this->translator->get('languages.fields.code'))
                ->required($this->translator->get('languages.validation.code_required'))
                ->maxLength(35, $this->translator->get('languages.validation.code_max_length'));
        }

        return $schema
            ->field('name', $this->translator->get('languages.fields.name'))
                ->required($this->translator->get('languages.validation.name_required'))
                ->maxLength(100, $this->translator->get('languages.validation.name_max_length'))
            ->field('flag_code', $this->translator->get('languages.fields.flag_code'))
                ->required($this->translator->get('languages.validation.flag_code_required'))
                ->regex('/\\A[A-Za-z]{2}\\z/D', $this->translator->get('languages.validation.flag_code_invalid'))
            ->field('sort_order', $this->translator->get('languages.fields.sort_order'))
                ->required($this->translator->get('languages.validation.sort_order_required'))
                ->integer($this->translator->get('languages.validation.sort_order_invalid'))
            ->end();
    }
}
