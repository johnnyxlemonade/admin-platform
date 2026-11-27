<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\System\Users\Validation\UserPasswordPolicy;
use Lemonade\Admin\System\Users\Validation\UserPasswordValidationSchema;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the self-service password validation contract
 */
final class UserPasswordValidationSchemaTest extends TestCase
{
    /**
     * Verifies the complete password change input and its framework rules
     */
    public function testItUsesFrameworkRulesForTheCompletePasswordChangeInput(): void
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);

        $fields = (new UserPasswordValidationSchema($translator))->changeOwnPassword()->fields();

        self::assertSame(['current_password', 'new_password', 'new_password_confirmation'], array_keys($fields));
        self::assertSame(['required'], array_map(static fn($rule): string => $rule->name(), $fields['current_password']->rules()));
        self::assertSame(['required', 'min_length'], array_map(static fn($rule): string => $rule->name(), $fields['new_password']->rules()));
        self::assertSame((string) UserPasswordPolicy::MIN_LENGTH, $fields['new_password']->rules()[1]->param());
        self::assertSame(['required', 'matches'], array_map(static fn($rule): string => $rule->name(), $fields['new_password_confirmation']->rules()));
        self::assertSame('new_password', $fields['new_password_confirmation']->rules()[1]->param());
    }
}
