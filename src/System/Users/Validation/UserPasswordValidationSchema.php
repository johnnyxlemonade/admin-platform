<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Validation;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Defines validation for the current user's password change form
 */
final readonly class UserPasswordValidationSchema
{
    /**
     * Creates the schema factory with localized validation messages
     */
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Builds validation for changing the current user's password
     */
    public function changeOwnPassword(): ValidationSchema
    {
        return ValidationSchema::create()
            ->field('current_password', $this->translator->get('users.profile.security.current_password'))
                ->required($this->translator->get('users.validation.current_password_required'))
            ->field('new_password', $this->translator->get('users.profile.security.new_password'))
                ->required($this->translator->get('users.validation.password_required'))
                ->minLength(UserPasswordPolicy::MIN_LENGTH, $this->translator->get('users.validation.password_min_length'))
            ->field('new_password_confirmation', $this->translator->get('users.profile.security.confirm_password'))
                ->required($this->translator->get('users.validation.password_confirmation_required'))
                ->matches('new_password', $this->translator->get('users.validation.password_confirmation_mismatch'))
            ->end();
    }
}
