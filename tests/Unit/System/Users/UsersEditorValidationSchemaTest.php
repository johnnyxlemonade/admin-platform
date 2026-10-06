<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\System\Users\Editor\UsersEditorValidationSchema;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class UsersEditorValidationSchemaTest extends TestCase
{
    public function testItDefinesOnlyTheUsersEditorFieldsWithFrameworkRules(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);

        $schema = (new UsersEditorValidationSchema($translator))->forUpdate(42);
        $fields = $schema->fields();

        self::assertSame(['first_name', 'last_name', 'email', 'phone', 'active', 'version', 'role', 'permissions', 'local_password'], array_keys($fields));
        self::assertSame('users.fields.first_name', $fields['first_name']->label());
        self::assertSame('users.fields.email', $fields['email']->label());
        self::assertSame('users.editor.active', $fields['active']->label());
        self::assertSame('users.editor.version', $fields['version']->label());
        self::assertSame('is_natural_no_zero', $fields['version']->rules()[1]->name());
        self::assertSame('system_users_unique_email', $fields['email']->rules()[3]->name());
        self::assertSame('system_users_role', $fields['role']->rules()[0]->name());
        self::assertSame('12', $fields['local_password']->rules()[0]->param());
    }

    public function testCreateRequiresOneRoleAndALocalPassword(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);

        $fields = (new UsersEditorValidationSchema($translator))->forCreate()->fields();

        self::assertSame(['first_name', 'last_name', 'email', 'phone', 'active', 'role', 'local_password'], array_keys($fields));
        self::assertSame('required', $fields['role']->rules()[0]->name());
        self::assertSame('system_users_role', $fields['role']->rules()[1]->name());
        self::assertSame('required', $fields['local_password']->rules()[0]->name());
    }
}
