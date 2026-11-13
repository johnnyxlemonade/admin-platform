<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Media;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\File\FileReadModel;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Presentation\AdminFileOriginalPathResolver;
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
 * Verifies attachment downloads from canonical private storage
 */
final class MediaDownloadControllerTest extends TestCase
{
    /**
     * Removes the temporary upload root after the test
     */
    protected function tearDown(): void
    {
        $basePath = sys_get_temp_dir() . '/lemonade-media-download-controller-test';
        if (is_file($basePath . '/storage/uploads/admin/files/program.pdf')) {
            unlink($basePath . '/storage/uploads/admin/files/program.pdf');
        }
        if (is_dir($basePath . '/storage/uploads/admin/files')) {
            rmdir($basePath . '/storage/uploads/admin/files');
        }
        if (is_dir($basePath . '/storage/uploads/admin')) {
            rmdir($basePath . '/storage/uploads/admin');
        }
        if (is_dir($basePath . '/storage/uploads')) {
            rmdir($basePath . '/storage/uploads');
        }
        if (is_dir($basePath . '/storage')) {
            rmdir($basePath . '/storage');
        }
        if (is_dir($basePath)) {
            rmdir($basePath);
        }
    }

    /**
     * Downloads an attachment using its stored relative path
     */
    public function testDownloadsGenericFileUsingStoredPathNameAndMimeType(): void
    {
        $basePath = sys_get_temp_dir() . '/lemonade-media-download-controller-test';
        mkdir($basePath . '/storage/uploads/admin/files', 0777, true);
        file_put_contents($basePath . '/storage/uploads/admin/files/program.pdf', 'PDF content');
        $controller = new MediaDownloadController(
            authorization: $this->authorization(),
            adminResponses: (new ReflectionClass(AdminResponseFactory::class))->newInstanceWithoutConstructor(),
            files: new AdminFileModel($this->driver(), new FileReadModel($this->driver())),
            originals: new AdminFileOriginalPathResolver(
                images: (new ReflectionClass(ImageVariantPathResolver::class))->newInstanceWithoutConstructor(),
                context: new ApplicationContext(Environment::Testing, new Path($basePath, $basePath . '/public'), DebugMode::disabled()),
            ),
            responses: $this->responses(),
        );

        $response = $controller->download(16, new ServerRequest('GET', '/admin/system/media/16/download'));

        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="Program podzimu.pdf"', $response->getHeaderLine('Content-Disposition'));
        self::assertSame('PDF content', $response->getBody()->getContents());
    }

    /**
     * Creates authorization for the media catalog
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
     * Creates a database driver returning attachment metadata
     */
    private function driver(): DatabaseDriverInterface
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturn($this->metadataResult());

        return $driver;
    }

    /**
     * Returns stored attachment metadata
     */
    private function metadataResult(): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $metadata = [
            'id' => 16,
            'module_code' => 'cms.page',
            'entity_id' => 7,
            'usage' => 'attachment',
            'kind' => 'file',
            'media_category' => 'document',
            'storage_path' => 'admin/files/program.pdf',
            'display_name' => 'Program podzimu.pdf',
            'original_filename' => 'program.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 11,
            'width' => null,
            'height' => null,
            'sort_order' => 0,
        ];
        $result->method('result_array')->willReturn([$metadata]);

        return $result;
    }

    /**
     * Creates Framework responses for file downloads
     */
    private function responses(): Responses
    {
        $factory = new Psr17Factory();

        return new Responses(new ResponseBuilder($factory, $factory));
    }
}
