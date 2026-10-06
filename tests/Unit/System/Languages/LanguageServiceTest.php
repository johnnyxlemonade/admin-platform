<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Languages\Models\LanguageModel;
use Lemonade\Admin\System\Languages\Services\LanguageService;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LanguageServiceTest extends TestCase
{
    /**
     * Overuje, ze ulozeni nezmeneneho jazyka nevytvari zapis ani auditni transakci
     */
    public function testIdenticalUpdateDoesNotWriteOrAudit(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = [
                'sql' => $sql,
                'bindings' => $bindings === false ? [] : array_values($bindings),
            ];

            return $this->databaseResult([[
                'id' => 4,
                'code' => 'cs',
                'name' => 'Čeština',
                'flag_code' => 'CZ',
                'enabled' => 1,
                'is_default' => 1,
                'sort_order' => 10,
            ]]);
        });
        $service = new LanguageService(
            new LanguageModel($driver),
            (new \ReflectionClass(TransactionalEventProcessor::class))->newInstanceWithoutConstructor(),
            $this->localActorGuard(),
        );

        $service->update(4, 'Čeština', 'cz', 10);

        self::assertCount(1, $queries);
        self::assertStringContainsString('SELECT', $queries[0]['sql']);
        self::assertStringNotContainsString('UPDATE', $queries[0]['sql']);
    }

    /**
     * Overuje, ze create odmitne case-insensitive variantu obsazeneho code
     */
    public function testCreateRejectsAnUppercaseVariantOfAnExistingLanguageCode(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = [
                'sql' => $sql,
                'bindings' => $bindings === false ? [] : array_values($bindings),
            ];

            return $this->databaseResult([['__exists' => 1]]);
        });
        $service = new LanguageService(
            new LanguageModel($driver),
            (new \ReflectionClass(TransactionalEventProcessor::class))->newInstanceWithoutConstructor(),
            $this->localActorGuard(),
        );

        try {
            $service->create('CS', 'Czech', 'CZ', false, 0);
            self::fail('An existing code must be rejected before persistence.');
        } catch (RuntimeException $exception) {
            self::assertSame('languages.validation.code_taken', $exception->getMessage());
        }

        self::assertCount(1, $queries);
        self::assertStringContainsString('LOWER(code) = ?', $queries[0]['sql']);
        self::assertSame(['cs'], $queries[0]['bindings']);
    }

    /**
     * Vytvari vysledek databazoveho dotazu s predanymi radky
     *
     * @param list<array<string, mixed>> $rows
     */
    private function databaseResult(array $rows): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn($rows);

        return $result;
    }

    /**
     * Vytvari guard pro auditovanou mutaci lokalniho uzivatele
     */
    private function localActorGuard(): LocalActorGuard
    {
        $user = new AuthenticatedUser(7, 'admin@example.test');

        return new LocalActorGuard(new class ($user) implements CurrentPrincipalProviderInterface {
            /**
             * Nastavuje lokalniho uzivatele pro testovaci mutaci
             */
            public function __construct(private AuthenticatedUser $user) {}

            /**
             * Vraci lokalni principal odpovidajici testovacimu uzivateli
             */
            public function currentPrincipal(): AdminPrincipalInterface
            {
                return new LocalAdminPrincipal($this->user);
            }

            /**
             * Vraci aktualniho lokalniho uzivatele
             */
            public function currentUser(): AuthenticatedUser
            {
                return $this->user;
            }
        });
    }
}
