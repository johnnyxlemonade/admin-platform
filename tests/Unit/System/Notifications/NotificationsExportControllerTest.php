<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Export\CsvExport;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationPresentation;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsExportController;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Overuje HTTP kontrakt CSV exportu oznameni
 */
final class NotificationsExportControllerTest extends TestCase
{
    /**
     * Overi CSV download hlavicky a iterovatelne telo odpovedi
     */
    public function testExportReturnsCsvAttachmentForExistingSelection(): void
    {
        $queryNumber = 0;
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queryNumber): DatabaseResultInterface {
            unset($sql, $bindings);
            $queryNumber++;

            return $this->databaseResult(match ($queryNumber) {
                1 => [['id' => 3, 'active' => 1, 'deleted_at' => null]],
                2 => [['id' => 3, 'title' => 'Udrzba', 'active' => 1, 'deleted_at' => null, 'created_at' => '2026-10-02 10:00:00', 'updated_at' => '2026-10-02 10:15:00', 'author_first_name' => 'Ada', 'author_last_name' => 'Admin', 'author_email' => 'ada@example.test']],
                3 => [['notification_id' => 3, 'role_name' => 'Editors']],
                default => [],
            });
        });
        $factory = new Psr17Factory();
        $responses = new Responses(new ResponseBuilder($factory, $factory));
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static function (string $key, array $parameters = []): string {
            return str_replace('{audience}', (string) ($parameters['audience'] ?? ''), $key);
        });
        $controller = new NotificationsExportController(
            authorization: $this->authorization(),
            adminResponses: (new ReflectionClass(AdminResponseFactory::class))->newInstanceWithoutConstructor(),
            notifications: new NotificationModel($driver),
            presentation: new NotificationPresentation(),
            csv: new CsvExport(),
            translator: $translator,
            responses: $responses,
        );

        $response = $controller->export((new ServerRequest('POST', '/admin/notification-management/export'))->withParsedBody(['ids' => ['3']]));

        self::assertSame(CsvExport::CONTENT_TYPE, $response->getHeaderLine('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename="notifications-\d{4}-\d{2}-\d{2}-\d{6}\.csv"$/', $response->getHeaderLine('Content-Disposition'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringStartsWith("\xEF\xBB\xBFID;", $response->getBody()->getContents());
        self::assertSame(4, $queryNumber);
    }

    /**
     * Vytvori autorizaci s opravnenim pro nacteni oznameni
     */
    private function authorization(): AuthorizationService
    {
        $principal = $this->createMock(CurrentPrincipalProviderInterface::class);
        $user = new AuthenticatedUser(7, 'admin@example.test');
        $principal->method('currentUser')->willReturn($user);
        $principal->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($user));
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn([['id' => 1]]);

        return new AuthorizationService(
            currentUser: $principal,
            database: new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
        );
    }

    /**
     * Vytvori vysledek databazoveho dotazu z predanych radku
     *
     * @param list<array<string, int|string|null>> $rows
     */
    private function databaseResult(array $rows): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn($rows);

        return $result;
    }
}
