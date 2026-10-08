<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Media;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Presentation\AdminFileImageAssetResolver;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Admin\System\Media\Http\Controller\MediaDownloadController;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Overuje download generic system_file z canonical upload storage
 */
final class MediaDownloadControllerTest extends TestCase
{
    /**
     * Odstrani docasny upload root po testu
     */
    protected function tearDown(): void
    {
        $basePath = sys_get_temp_dir() . '/lemonade-media-download-controller-test';
        if (is_file($basePath . '/public/uploads/admin/files/program.pdf')) {
            unlink($basePath . '/public/uploads/admin/files/program.pdf');
        }
        if (is_dir($basePath . '/public/uploads/admin/files')) {
            rmdir($basePath . '/public/uploads/admin/files');
        }
        if (is_dir($basePath . '/public/uploads/admin')) {
            rmdir($basePath . '/public/uploads/admin');
        }
        if (is_dir($basePath . '/public/uploads')) {
            rmdir($basePath . '/public/uploads');
        }
        if (is_dir($basePath . '/public')) {
            rmdir($basePath . '/public');
        }
        if (is_dir($basePath)) {
            rmdir($basePath);
        }
    }

    /**
     * Generic system_file se stahuje z ulozene relativni storage cesty
     */
    public function testDownloadsGenericFileUsingStoredPathNameAndMimeType(): void
    {
        $basePath = sys_get_temp_dir() . '/lemonade-media-download-controller-test';
        mkdir($basePath . '/public/uploads/admin/files', 0777, true);
        file_put_contents($basePath . '/public/uploads/admin/files/program.pdf', 'PDF content');
        $controller = new MediaDownloadController(
            authorization: $this->authorization(),
            adminResponses: (new ReflectionClass(AdminResponseFactory::class))->newInstanceWithoutConstructor(),
            files: new AdminFileModel($this->driver()),
            assets: (new ReflectionClass(AdminFileImageAssetResolver::class))->newInstanceWithoutConstructor(),
            paths: (new ReflectionClass(ImageVariantPathResolver::class))->newInstanceWithoutConstructor(),
            context: new ApplicationContext(Environment::Testing, new Path($basePath, $basePath . '/public'), DebugMode::disabled()),
            responses: $this->responses(),
        );

        $response = $controller->download(16, new ServerRequest('GET', '/admin/system/media/16/download'));

        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="Program podzimu.pdf"', $response->getHeaderLine('Content-Disposition'));
        self::assertSame('PDF content', $response->getBody()->getContents());
    }

    /**
     * Vytvori autorizaci s opravnenim pro Media katalog
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
     * Vytvori database driver vracejici generic file metadata
     */
    private function driver(): DatabaseDriverInterface
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturn($this->metadataResult());

        return $driver;
    }

    /**
     * Vrati ulozene metadata generic prilohy
     */
    private function metadataResult(): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $metadata = [
            'id' => 16,
            'kind' => 'file',
            'storage_path' => 'admin/files/program.pdf',
            'display_name' => 'Program podzimu.pdf',
            'original_filename' => 'program.pdf',
            'mime_type' => 'application/pdf',
        ];
        $result->method('result_array')->willReturn([$metadata]);

        return $result;
    }

    /**
     * Vytvori frameworkovou odpoved pro download souboru
     */
    private function responses(): Responses
    {
        $factory = new Psr17Factory();

        return new Responses(new ResponseBuilder($factory, $factory));
    }
}
