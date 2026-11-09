<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\Contract\EditorAccessPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\File\FileReadModel;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\ModuleProviderResolver;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Presentation\AdminFileCapabilities;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\Presentation\Http\Controller\AdminFileUploadController;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Chunk\ChunkUploadConfig;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\Chunk\ChunkUploadSession;
use Lemonade\Framework\Upload\Chunk\FilesystemChunkUploadSessionStore;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\Validation\ValidationSchema;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Overuje HTTP rozhodnuti sdileneho file controlleru pri cteni a vypnuti usage
 */
final class AdminFileUploadControllerAuthorizationTest extends TestCase
{
    private string $basePath = '';

    /**
     * Vytvori izolovane docasne uloziste pro upload session
     */
    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-admin-file-auth-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public', 0700, true);
    }

    /**
     * Odstrani docasne upload session po kazdem scenari
     */
    protected function tearDown(): void
    {
        (new DirectoryManager())->delete($this->basePath);
    }

    /**
     * Ctenar muze nacist aktivni kolekci
     */
    public function testReadOnlyUserCanReadEnabledCollection(): void
    {
        $usages = $this->usages(true);
        $controller = $this->controller(false, $usages, uploads: $this->emptyUploads());

        $response = $controller->collection('system.users', 'gallery', 7);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], json_decode((string) $response->getBody(), true)['files']);
    }

    /**
     * Vraci operace, ktere ctenar nesmi provest ani pri aktivni usage
     *
     * @return iterable<string,array{string}>
     */
    public static function mutatingActions(): iterable
    {
        yield 'start' => ['start'];
        yield 'remove' => ['remove'];
        yield 'reorder' => ['reorder'];
        yield 'rename' => ['rename'];
    }

    /**
     * Cteci opravneni nepovoluje file mutace
     */
    #[DataProvider('mutatingActions')]
    public function testReadOnlyUserCannotMutateEnabledCollection(string $action): void
    {
        $controller = $this->controller(false, $this->usages(true));

        self::assertSame(403, $this->actionStatus($controller, $action, 'gallery'));
    }

    /**
     * Ctenar nesmi pokracovat v drive zalozene upload session
     */
    public function testReadOnlyUserCannotAppendOrCompleteExistingSession(): void
    {
        $store = $this->store(new ChunkUploadConfig());
        $session = $this->session('gallery');
        $store->create($session);
        $controller = $this->controller(false, $this->usages(true), $this->chunks($store));

        self::assertSame(403, $controller->append($session->uploadId(), new ServerRequest('PATCH', '/admin/file'))->getStatusCode());
        self::assertSame(403, $controller->complete($session->uploadId())->getStatusCode());
        self::assertSame(0, $store->payloadSize($session->uploadId()));
        self::assertNotNull($store->load($session->uploadId()));
    }

    /**
     * Vraci obě usage, ktere mohou byt nezavisle vypnute
     *
     * @return iterable<string,array{string}>
     */
    public static function mediaUsages(): iterable
    {
        yield 'gallery' => ['gallery'];
        yield 'attachments' => ['attachment'];
    }

    /**
     * Vypnuta usage odmitne cteni i mutace pres HTTP controller
     */
    #[DataProvider('mediaUsages')]
    public function testDisabledUsageRejectsCollectionAndMutations(string $usage): void
    {
        $controller = $this->controller(true, new AdminFileUsageRegistry());

        self::assertSame(422, $controller->collection('system.users', $usage, 7)->getStatusCode());
        foreach (['start', 'remove', 'reorder'] as $action) {
            self::assertSame(422, $this->actionStatus($controller, $action, $usage));
        }
        self::assertSame(404, $this->actionStatus($controller, 'rename', $usage));
    }

    /**
     * Dokonceni po vypnuti usage nic nepublikuje a session lze uklidit
     */
    #[DataProvider('mediaUsages')]
    public function testCompletionAfterDisablingUsageLeavesOnlyAbortableTemporarySession(string $usage): void
    {
        $store = $this->store(new ChunkUploadConfig());
        $session = $this->session($usage);
        $store->create($session);
        $store->append($session, 'data');
        $controller = $this->controller(true, new AdminFileUsageRegistry(), $this->chunks($store));

        self::assertSame(422, $controller->complete($session->uploadId())->getStatusCode());
        self::assertNotNull($store->load($session->uploadId()));
        self::assertSame(4, $store->payloadSize($session->uploadId()));

        self::assertSame(200, $controller->abort($session->uploadId())->getStatusCode());
        self::assertNull($store->load($session->uploadId()));
        self::assertFileDoesNotExist($store->payloadPath($session->uploadId()));
    }

    /**
     * Opustenou session odstrani existujici expiracni cleanup
     */
    public function testExpiredDisabledUsageSessionIsReclaimedByExistingCleanup(): void
    {
        $config = new ChunkUploadConfig();
        $store = $this->store($config);
        $session = $this->session('gallery', time() - 7200);
        $store->create($session);
        $store->append($session, 'data');

        self::assertSame(['removed_sessions' => 1, 'released_bytes' => 4], $this->chunks($store, $config)->cleanupExpired());
        self::assertNull($store->load($session->uploadId()));
        self::assertFileDoesNotExist($store->payloadPath($session->uploadId()));
    }

    /**
     * Vyvola verejny endpoint bez site routeru a middleware
     */
    private function actionStatus(AdminFileUploadController $controller, string $action, string $usage): int
    {
        $request = new ServerRequest('POST', '/admin/file');

        return match ($action) {
            'start' => $controller->start('system.users', $usage, 7, $request)->getStatusCode(),
            'remove' => $controller->removeCollectionFile('system.users', $usage, 7, 19)->getStatusCode(),
            'reorder' => $controller->reorder('system.users', $usage, 7, $request)->getStatusCode(),
            'rename' => $controller->rename('system.users', $usage, 7, 19, $request)->getStatusCode(),
            default => throw new \InvalidArgumentException('Unknown file action.'),
        };
    }

    /**
     * Sestavi controller s realnou autorizaci a jen potrebnymi zavislostmi
     */
    private function controller(bool $canSave, AdminFileUsageRegistry $usages, ?ChunkUploadService $chunks = null, ?AdminFileUploadComponent $uploads = null): AdminFileUploadController
    {
        $reflection = new ReflectionClass(AdminFileUploadController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('editors')->setValue($controller, $this->dispatcher($canSave));
        $reflection->getProperty('usages')->setValue($controller, $usages);
        $factory = new Psr17Factory();
        $reflection->getProperty('responses')->setValue($controller, new Responses(new ResponseBuilder($factory, $factory)));
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnArgument(0);
        $reflection->getProperty('translator')->setValue($controller, $translator);
        if ($chunks !== null) {
            $reflection->getProperty('chunks')->setValue($controller, $chunks);
        }
        if ($uploads !== null) {
            $reflection->getProperty('uploads')->setValue($controller, $uploads);
        }

        return $controller;
    }

    /**
     * Vytvori skutecny dispatcher nad editorem s oddelenym ctenim a ulozenim
     */
    private function dispatcher(bool $canSave): EditorDispatcher
    {
        $provider = new class ($canSave) implements EditorProviderInterface, EditorAccessPolicyInterface {
            public function __construct(private bool $canSave) {}

            public function editorDefinition(): EditorDefinition
            {
                return new EditorDefinition('system.users.view', 'system.users.edit', 'system.users.create', []);
            }

            public function loadAccessDecision(int $id): bool
            {
                return true;
            }

            public function saveAccessDecision(int $id): bool
            {
                return $this->canSave;
            }

            public function updateData(int $id): array
            {
                return ['id' => $id];
            }

            public function createData(): array
            {
                return [];
            }

            public function createValidationSchema(): ValidationSchema
            {
                return ValidationSchema::create();
            }

            public function updateValidationSchema(int $id): ValidationSchema
            {
                return ValidationSchema::create();
            }

            public function create(array $data): EditorSaveResult
            {
                throw new \LogicException('Not used.');
            }

            public function update(int $id, array $data): EditorSaveResult
            {
                throw new \LogicException('Not used.');
            }
        };
        $container = new Container();
        $container->instance('file-authorization-editor', $provider);
        $container->freeze();
        $modules = new AdminModuleRegistry(new AdminNavigationGroupRegistry());
        $modules->register(new UsersModuleDefinition());
        $editors = new EditorRegistry();
        $editors->register('system.users', 'file-authorization-editor');
        $resolver = new ModuleProviderResolver(new DataGridRegistry(), $editors, new ModulePageRegistry(), new ModuleActionRegistry(), $container);

        return new EditorDispatcher(
            $modules,
            $resolver,
            (new ReflectionClass(AuthorizationService::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(FormValidation::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(EditorLockManager::class))->newInstanceWithoutConstructor(),
        );
    }

    /**
     * Prida standardni gallery a attachment usages
     */
    private function usages(bool $enabled): AdminFileUsageRegistry
    {
        $usages = new AdminFileUsageRegistry();
        $usages->registerCapabilities('system.users', new AdminFileCapabilities(gallery: $enabled, attachments: $enabled));

        return $usages;
    }

    /**
     * Vrati prazdnou kolekci bez zapisove databaze
     */
    private function emptyUploads(): AdminFileUploadComponent
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn([]);
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnArgument(0);
        $driver->method('query')->willReturn($result);
        $files = new AdminFileModel($driver, new FileReadModel($driver));
        $reflection = new ReflectionClass(AdminFileUploadComponent::class);
        $uploads = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('files')->setValue($uploads, $files);

        return $uploads;
    }

    /**
     * Vytvori session se stabilnim vlastnikem a usage v serverovem kontextu
     */
    private function session(string $usage, ?int $createdAt = null): ChunkUploadSession
    {
        $createdAt ??= time();

        return new ChunkUploadSession(
            uploadId: bin2hex(random_bytes(32)),
            kind: $usage === 'gallery' ? 'image' : 'file',
            profile: $usage === 'gallery' ? 'admin-image' : 'admin-file',
            originalFilename: $usage === 'gallery' ? 'image.png' : 'note.txt',
            declaredSize: 4,
            maxBytes: 8,
            currentOffset: 0,
            createdAt: $createdAt,
            expiresAt: $createdAt + 3600,
            context: ['module_code' => 'system.users', 'entity_id' => 7, 'usage' => $usage],
            publicationToken: bin2hex(random_bytes(32)),
        );
    }

    /**
     * Pripravi skutecne docasne uloziste frameworkove chunk session
     */
    private function store(ChunkUploadConfig $config): FilesystemChunkUploadSessionStore
    {
        $directoryManager = new DirectoryManager();
        $filesystem = new Filesystem($directoryManager, new FileManager(), new LockManager($directoryManager));
        $context = (new ApplicationContextFactory())->create($this->basePath);

        return new FilesystemChunkUploadSessionStore($filesystem, new UploadPathPolicy($context), $config);
    }

    /**
     * Pouzije jen session a cleanup cast skutecne frameworkove sluzby
     */
    private function chunks(FilesystemChunkUploadSessionStore $store, ?ChunkUploadConfig $config = null): ChunkUploadService
    {
        $reflection = new ReflectionClass(ChunkUploadService::class);
        $chunks = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('store')->setValue($chunks, $store);
        $reflection->getProperty('config')->setValue($chunks, $config ?? new ChunkUploadConfig());

        return $chunks;
    }
}
