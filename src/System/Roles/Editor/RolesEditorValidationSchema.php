<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Editor;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Sestavuje vstupni validaci create a update editoru roli
 */
final class RolesEditorValidationSchema
{
    /**
     * Nastavuje preklady validacnich zprav editoru
     */
    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * Sestavuje schema vcetne kodu nove role
     */
    public function forCreate(): ValidationSchema
    {
        return $this->schema(true);
    }

    /**
     * Sestavuje schema bez nemenitelneho kodu existujici role
     */
    public function forUpdate(): ValidationSchema
    {
        return $this->schema(false);
    }

    /**
     * Sestavi spolecnou validaci s volitelnym polem kodu
     */
    private function schema(bool $create): ValidationSchema
    {
        $schema = ValidationSchema::create();
        if ($create) {
            $schema->field('code', $this->translator->get('roles.fields.code'))
                ->required($this->translator->get('roles.validation.code_required'))
                ->maxLength(100, $this->translator->get('roles.validation.code_invalid'));
        }

        return $schema
            ->field('name', $this->translator->get('roles.fields.name'))
                ->required($this->translator->get('roles.validation.name_required'))
                ->maxLength(150, $this->translator->get('roles.validation.name_invalid'))
            ->field('description', $this->translator->get('roles.fields.description'))
                ->maxLength(65535, $this->translator->get('roles.validation.description_invalid'))
            ->field('permissions', $this->translator->get('roles.fields.permissions'))
                ->custom('system_roles_permissions', message: $this->translator->get('roles.validation.permissions'))
            ->end();
    }
}
