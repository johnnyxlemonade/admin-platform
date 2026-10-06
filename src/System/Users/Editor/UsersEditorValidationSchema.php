<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Editor;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Definuje tvarovou validaci create a update vstupu Users editoru
 */
final class UsersEditorValidationSchema
{
    /**
     * Nastavuje prekladovy zdroj chyb editorove validace
     */
    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * Sestavuje schema pro zmenu existujiciho uzivatele vcetne role a overrides
     */
    public function forUpdate(int $id): ValidationSchema
    {
        return ValidationSchema::create()
            ->field('first_name', $this->translator->get('users.fields.first_name'))
                ->required($this->translator->get('users.validation.first_name_required'))
                ->maxLength(100, $this->translator->get('users.validation.first_name_max_length'))
            ->field('last_name', $this->translator->get('users.fields.last_name'))
                ->required($this->translator->get('users.validation.last_name_required'))
                ->maxLength(100, $this->translator->get('users.validation.last_name_max_length'))
            ->field('email', $this->translator->get('users.fields.email'))
                ->required($this->translator->get('users.validation.email_required'))
                ->email($this->translator->get('users.validation.email_invalid'))
                ->maxLength(254, $this->translator->get('users.validation.email_max_length'))
                ->custom('system_users_unique_email', (string) $id, $this->translator->get('users.validation.email_taken'))
            ->field('phone', $this->translator->get('users.fields.phone'))
                ->maxLength(35, $this->translator->get('users.validation.phone_max_length'))
            ->field('active', $this->translator->get('users.editor.active'))
                ->required($this->translator->get('users.validation.status_required'))
                ->inList(['0', '1'], $this->translator->get('users.validation.status_invalid'))
            ->field('version', $this->translator->get('users.editor.version'))
                ->required($this->translator->get('users.validation.version_required'))
                ->isNaturalNoZero($this->translator->get('users.validation.version_invalid'))
            ->field('role', $this->translator->get('users.fields.role'))
                ->custom('system_users_role', message: $this->translator->get('users.validation.role_invalid'))
            ->field('permissions', $this->translator->get('users.editor.tabs.permissions'))
                ->custom('system_users_permission_overrides', message: $this->translator->get('users.validation.permission_invalid'))
            ->field('local_password', $this->translator->get('users.fields.password'))
                ->minLength(12, $this->translator->get('users.validation.password_min_length'))
            ->end();
    }

    /**
     * Sestavuje schema pro zalozeni lokalniho uzivatele s roli a heslem
     */
    public function forCreate(): ValidationSchema
    {
        return ValidationSchema::create()
            ->field('first_name', $this->translator->get('users.fields.first_name'))
                ->required($this->translator->get('users.validation.first_name_required'))
                ->maxLength(100, $this->translator->get('users.validation.first_name_max_length'))
            ->field('last_name', $this->translator->get('users.fields.last_name'))
                ->required($this->translator->get('users.validation.last_name_required'))
                ->maxLength(100, $this->translator->get('users.validation.last_name_max_length'))
            ->field('email', $this->translator->get('users.fields.email'))
                ->required($this->translator->get('users.validation.email_required'))
                ->email($this->translator->get('users.validation.email_invalid'))
                ->maxLength(254, $this->translator->get('users.validation.email_max_length'))
                ->custom('system_users_unique_email', '0', $this->translator->get('users.validation.email_taken'))
            ->field('phone', $this->translator->get('users.fields.phone'))
                ->maxLength(35, $this->translator->get('users.validation.phone_max_length'))
            ->field('active', $this->translator->get('users.editor.active'))
                ->required($this->translator->get('users.validation.status_required'))
                ->inList(['0', '1'], $this->translator->get('users.validation.status_invalid'))
            ->field('role', $this->translator->get('users.fields.role'))
                ->required($this->translator->get('users.validation.role_required'))
                ->custom('system_users_role', message: $this->translator->get('users.validation.role_invalid'))
            ->field('local_password', $this->translator->get('users.fields.password'))
                ->required($this->translator->get('users.validation.password_required'))
                ->minLength(12, $this->translator->get('users.validation.password_min_length'))
            ->end();
    }
}
