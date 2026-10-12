<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Media;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Admin\Presentation\AdminThumbnailComponent;
use Lemonade\Admin\System\Media\DataGrid\MediaDataGrid;
use Lemonade\Admin\System\Media\Services\MediaService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class MediaDataGridActionsContractTest extends TestCase
{
    public function testEveryCatalogFileHasCanonicalDownloadAndEditorsReceiveTheSharedModalAction(): void
    {
        $row = $this->row(canEdit: true);

        self::assertSame(['download', 'edit'], array_column($row['actions'], 'key'));
        self::assertSame('/admin/system/media/18/download', $row['actions'][0]['url']);
        self::assertSame('navigate', $row['actions'][0]['kind']);
        self::assertSame('/admin/system/media/18/presentation', $row['actions'][1]['modalUrl']);
        self::assertSame('modal', $row['actions'][1]['kind']);
        self::assertTrue($row['actions'][1]['refresh']);
        self::assertSame('Program', $row['cells']['file'][0]['value']);
        self::assertNull($row['cells']['file'][0]['url']);
        self::assertArrayNotHasKey('download', $row['cells']['file'][0]);
    }

    public function testViewOnlyCatalogUserCannotReceiveThePresentationAction(): void
    {
        $row = $this->row(canEdit: false);

        self::assertSame(['download'], array_column($row['actions'], 'key'));
    }

    /** @return array<string,mixed> */
    private function row(bool $canEdit): array
    {
        $grid = new MediaDataGrid(
            media: (new ReflectionClass(MediaService::class))->newInstanceWithoutConstructor(),
            modules: (new ReflectionClass(ModuleStateResolver::class))->newInstanceWithoutConstructor(),
            thumbnails: (new ReflectionClass(AdminThumbnailComponent::class))->newInstanceWithoutConstructor(),
            authorization: $this->authorization($canEdit),
            translator: $this->translator(),
            urls: $this->urls(),
        );
        $row = (new ReflectionMethod($grid, 'row'))->invoke($grid, [
            'id' => 18,
            'module_code' => 'cms.news',
            'entity_id' => 42,
            'usage' => 'attachment',
            'kind' => 'file',
            'media_category' => 'document',
            'original_filename' => 'program.pdf',
            'display_name' => 'Program',
            'caption' => null,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'width' => null,
            'height' => null,
            'created_at' => '2026-10-12 10:00:00',
        ]);
        self::assertInstanceOf(DataGridRowDefinition::class, $row);

        return $row->toArray();
    }

    private function authorization(bool $canEdit): AuthorizationService
    {
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $user = new AuthenticatedUser(7, 'admin@example.test');
        $principals->method('currentUser')->willReturn($user);
        $principals->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($user));
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(static function (string $sql) use ($canEdit): array {
            if (str_contains($sql, 'r.is_super_admin=1')) {
                return [];
            }
            if (str_contains($sql, 'system_role_permission')) {
                return $canEdit ? [[
                    'code' => 'system.media.edit',
                    'name_key' => 'media.permissions.edit',
                    'module_code' => 'system.media',
                ]] : [];
            }

            return [];
        });

        return new AuthorizationService(
            currentUser: $principals,
            database: new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
        );
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnArgument(0);

        return $translator;
    }

    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->getNamed('admin.media.download', '/admin/system/media/{file}/download', ControllerAction::for('TestController', 'download'));
        $router->getNamed('admin.media.presentation.modal', '/admin/system/media/{file}/presentation', ControllerAction::for('TestController', 'modal'));

        return new UrlGenerator(router: $router);
    }
}
