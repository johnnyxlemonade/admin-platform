<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use PHPUnit\Framework\TestCase;

final class UsersProfileTopbarContractTest extends TestCase
{
    /**
     * Overuje, ze topbar odkazuje na canonical self-service profil bez Users ID
     */
    public function testTopbarUsesCanonicalProfileRoute(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 4) . '/src/Resources/views/layouts/admin.php');

        self::assertIsString($layout);
        self::assertStringContainsString('$helpers->url(\'admin.system.users.profile\')', $layout);
        self::assertStringNotContainsString('$helpers->url(\'admin.system.module.edit\', [\'module\' => \'users\'', $layout);
    }
}
