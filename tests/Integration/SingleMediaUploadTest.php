<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Integration;

use Lemonade\Admin\Audit\AuditLogService;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\File\FileReadModel;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Platform\Migrations\CreateCoreFiles;
use Lemonade\Admin\Presentation\AdminFileMutationService;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseDriver;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\ImageVariantCache;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Image\ImageOriginalMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Overuje realne single replacementy, audit, cleanup a soubezne procesy v izolovane DB
 */
final class SingleMediaUploadTest extends TestCase
{
    private string $root = '';
    // @phpstan-ignore property.uninitialized (PHPUnit setUp and worker connect initialize these fixtures)
    private Database $database;
    // @phpstan-ignore property.uninitialized (PHPUnit setUp and worker connect initialize these fixtures)
    private AdminFileModel $files;
    // @phpstan-ignore property.uninitialized (PHPUnit setUp and worker connect initialize these fixtures)
    private AdminFileMutationService $service;
    // @phpstan-ignore property.uninitialized (PHPUnit setUp and worker connect initialize these fixtures)
    private ApplicationContext $context;
    // @phpstan-ignore property.uninitialized (PHPUnit setUp and worker connect initialize these fixtures)
    private Filesystem $filesystem;
    // @phpstan-ignore property.uninitialized (PHPUnit setUp and worker connect initialize these fixtures)
    private ImageVariantPathResolver $paths;

    /**
     * Pripravi pouze vyhrazenou testovaci databazi a docasne originaly
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-media-integration-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/public', 0700, true);
        $this->connect();
        $this->database->statement('DELETE FROM system_file');
        $this->database->statement('DELETE FROM system_audit_log');
    }

    /**
     * Odstrani jen filesystem vytvoreny timto testem
     */
    protected function tearDown(): void
    {
        (new DirectoryManager())->delete($this->root);
    }

    /**
     * Sestavi produkcni persistence a mutace na samostatnem spojeni
     */
    private function connect(): void
    {
        $name = getenv('ADMIN_MEDIA_INTEGRATION_DATABASE');
        if (!is_string($name) || !str_starts_with($name, 'admin_media_test_')) {
            self::fail('Set ADMIN_MEDIA_INTEGRATION_DATABASE to a dedicated admin_media_test_ database.');
        }
        $config = DatabaseConfig::fromArray([
            'driver' => 'mysql', 'host' => getenv('ADMIN_MEDIA_INTEGRATION_HOST') !== false ? getenv('ADMIN_MEDIA_INTEGRATION_HOST') : 'db',
            'database' => $name, 'username' => 'db', 'password' => 'db',
        ]);
        $connection = new MysqlConnection($config, new DatabaseInstrumentation());
        $escaper = new MysqlIdentifierEscaper('');
        $driver = new MysqlDatabaseDriver($connection, $config, $escaper, new IdentifierProtector($escaper));
        $this->database = new Database($connection, $driver);
        $schema = new Schema(new SchemaCompiler(new MysqlSchemaGrammar(new MysqlSqlEscaper($escaper), $config)), $driver);
        (new CreateCoreFiles())->up($schema);
        $this->database->statement('CREATE TABLE IF NOT EXISTS system_audit_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_code VARCHAR(255), module_code VARCHAR(255), entity_type VARCHAR(255), entity_key VARCHAR(255), actor_type VARCHAR(30), actor_key VARCHAR(255), actor_user_id BIGINT UNSIGNED, payload TEXT, created_at DATETIME) ENGINE=InnoDB');
        $this->files = new AdminFileModel($driver, new FileReadModel($driver));
        $actor = new AuthenticatedUser(7, 'editor@example.test');
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn($actor);
        $principals->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($actor));
        $directory = new DirectoryManager();
        $this->filesystem = new Filesystem($directory, new FileManager(), new LockManager($directory));
        $this->context = new ApplicationContext(Environment::Testing, new Path($this->root, $this->root . '/public'), DebugMode::disabled());
        $this->paths = new ImageVariantPathResolver($this->context, new DirectoryPathGenerator());
        $this->service = new AdminFileMutationService(
            $this->files,
            new TransactionalEventProcessor($this->database, new AuditLogService($this->database), new DomainEventDispatcher(new NullLogger())),
            new LocalActorGuard($principals),
            $this->paths,
            new ImageVariantCache($this->filesystem, $this->paths),
            $this->filesystem,
            $this->context,
            $this->database,
        );
    }

    /**
     * Vytvori vystup existujici generic upload pipeline
     */
    private function upload(string $name): UploadedFile
    {
        $path = $this->context->uploadPath($name);
        $this->filesystem->write($path, 'document', 0600);
        return new UploadedFile($name, $path, $this->context->uploadRelativePath() . '/' . $name, 'application/pdf', 8);
    }

    /**
     * Obe zmeny druhu zachovaji jedinou file identitu a uklidi stary original
     */
    public function testSingleReplacementAcrossKindsAndOtherTargets(): void
    {
        $first = $this->upload('first.pdf');
        $id = $this->service->saveGenericUpload('cms.type_1', 1, 'field_1', 'first.pdf', $first, false);
        $other = $this->service->saveGenericUpload('cms.type_1', 1, 'field_2', 'other.pdf', $this->upload('other.pdf'), false);
        $imagePath = $this->paths->originalPath(new \Lemonade\Framework\Image\Value\ImageAsset('asset', $this->paths->originalReference('asset', 'version', ImageOriginalStorage::Storage, ImageFormat::Png, new ImageDimensions(1, 1))));
        $this->filesystem->write($imagePath, 'image', 0600);
        self::assertSame($id, $this->service->saveImageUpload('cms.type_1', 1, 'field_1', 'asset', 'version', new ImageOriginalMetadata('image.png', ImageFormat::Png, 5, 1, 1)));
        self::assertFileDoesNotExist($first->storedPath());
        $image = $this->files->findForEntity('cms.type_1', 1, 'field_1');
        self::assertNotNull($image);
        self::assertSame('image', $image['kind']);
        self::assertNull($image['storage_path']);
        self::assertSame($id, $this->service->saveGenericUpload('cms.type_1', 1, 'field_1', 'last.pdf', $this->upload('last.pdf'), false));
        self::assertFileDoesNotExist($imagePath);
        $file = $this->files->findForEntity('cms.type_1', 1, 'field_1');
        self::assertNotNull($file);
        self::assertSame('file', $file['kind']);
        self::assertNull($file['asset_id']);
        self::assertNull($file['source_version']);
        self::assertSame([$id], array_column($this->files->listForEntity('cms.type_1', 1, 'field_1'), 'id'));
        self::assertNotNull($this->files->findForTarget('cms.type_1', 1, 'field_2', $other));
        self::assertNull($this->files->findForTarget('cms.type_1', 2, 'field_1', $id));
        $this->service->updateTargetFilePresentation('cms.type_1', 1, 'field_1', $id, 'Document', 'Caption');
        $renamed = $this->files->findForEntity('cms.type_1', 1, 'field_1');
        self::assertNotNull($renamed);
        self::assertSame('Document', $renamed['display_name']);
        $this->service->removeTargetFile('cms.type_1', 1, 'field_1', $id);
        self::assertSame([], $this->files->listForEntity('cms.type_1', 1, 'field_1'));
        self::assertFileDoesNotExist($this->context->uploadPath('last.pdf'));
        self::assertCount(6, $this->database->table('system_audit_log')->getArray());
    }

    /**
     * Multiple kolekce prijme vice originalu a povoli pouze uplne poradi sveho targetu
     */
    public function testMultipleOrderAndInvalidTarget(): void
    {
        $ids = [];
        foreach (['one.pdf', 'two.pdf'] as $name) {
            $ids[] = $this->service->saveGenericUpload('cms.type_1', 1, 'field_3', $name, $this->upload($name), true);
        }
        $this->service->reorder('cms.type_1', 1, 'field_3', array_reverse($ids));
        self::assertSame(array_reverse($ids), array_column($this->files->listForEntity('cms.type_1', 1, 'field_3'), 'id'));
        try {
            $this->service->removeTargetFile('cms.type_1', 1, 'field_4', $ids[0]);
            self::fail('Foreign target accepted.');
        } catch (\RuntimeException) {
            self::assertCount(2, $this->files->listForEntity('cms.type_1', 1, 'field_3'));
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->service->reorder('cms.type_1', 1, 'field_3', [$ids[0], $ids[0]]);
    }

    /**
     * Dva procesy cekaji na stejny prazdny target a po uvolneni vytvori jedinou identitu
     */
    public function testConcurrentSingleUploadsKeepOneFileAndReleaseLockOnFailure(): void
    {
        $children = [];
        $this->files->withSingleTargetLock('cms.type_1', 1, 'field_9', function () use (&$children): void {
            for ($index = 0; $index < 2; ++$index) {
                $code = 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . '; require ' . var_export(__FILE__, true) . '; '
                    . '\\' . self::class . '::uploadWorker(' . var_export($this->root, true) . ', ' . $index . ');';
                $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->root . '/output-' . $index, 'w'], 2 => ['file', $this->root . '/failure-' . $index, 'w']], $pipes);
                self::assertIsResource($process);
                $children[] = $process;
            }
            $deadline = microtime(true) + 5;
            while ((!is_file($this->root . '/ready-0') || !is_file($this->root . '/ready-1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            self::assertFileExists($this->root . '/ready-0');
            self::assertFileExists($this->root . '/ready-1');
            self::assertSame([], $this->files->listForEntity('cms.type_1', 1, 'field_9'));
        });
        foreach ($children as $index => $process) {
            self::assertSame(0, proc_close($process), (string) file_get_contents($this->root . '/failure-' . $index));
        }
        $this->connect();
        $files = $this->files->listForEntity('cms.type_1', 1, 'field_9');
        self::assertCount(1, $files);
        self::assertCount(2, $this->database->table('system_audit_log')->getArray());
        self::assertSame(1, (int) is_file($this->context->uploadPath('0.pdf')) + (int) is_file($this->context->uploadPath('1.pdf')));
        try {
            $this->files->withSingleTargetLock('cms.type_1', 1, 'field_9', static fn() => throw new \RuntimeException('rollback'));
        } catch (\RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }
        $this->connect();
        self::assertSame(42, $this->files->withSingleTargetLock('cms.type_1', 1, 'field_9', static fn(): int => 42));
    }

    /**
     * Spusti samostatny PHP proces bez zdedeneho databazoveho spojeni
     */
    public static function uploadWorker(string $root, int $index): void
    {
        $test = new self('testConcurrentSingleUploadsKeepOneFileAndReleaseLockOnFailure');
        $test->root = $root;
        $test->connect();
        file_put_contents($root . '/ready-' . $index, 'ready');
        $test->service->saveGenericUpload('cms.type_1', 1, 'field_9', $index . '.pdf', $test->upload($index . '.pdf'), false);
    }
}
