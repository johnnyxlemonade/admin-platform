<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionIcon;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\System\Languages\DataGrid\LanguagesDataGrid;
use Lemonade\Admin\System\Languages\Models\LanguageModel;
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

final class LanguagesDataGridActionsContractTest extends TestCase
{
    public function testDefaultLanguageHasOnlySecondaryModalEditMenuAction(): void
    {
        $actions = $this->rowActions(
            language: $this->language(enabled: true, isDefault: true),
            canManage: true,
            availableActions: ['enable', 'disable', 'set-default'],
        );

        self::assertCount(1, $actions);
        self::assertSame('edit', $actions[0]['key']);
        self::assertSame('modal', $actions[0]['kind']);
        self::assertSame('secondary', $actions[0]['placement']);
        self::assertSame('/admin/api/modal/languages/15/edit', $actions[0]['modalUrl']);
        self::assertSame('medium', $actions[0]['modalSize']);
        self::assertSame('pencil-square', $actions[0]['icon']);
        self::assertTrue($actions[0]['refresh']);
    }

    public function testEnabledNonDefaultLanguageHasOnlySecondaryMenuActions(): void
    {
        $actions = $this->rowActions(
            language: $this->language(enabled: true, isDefault: false),
            canManage: true,
            availableActions: ['disable', 'set-default'],
        );

        self::assertSame(['edit', 'disable', 'set-default'], array_column($actions, 'key'));
        self::assertSame('secondary', $actions[0]['placement']);
        self::assertSame('modal', $actions[0]['kind']);
        self::assertSame('pencil-square', $actions[0]['icon']);
        self::assertSame('secondary', $actions[1]['placement']);
        self::assertSame('mutation', $actions[1]['kind']);
        self::assertSame('pause-circle', $actions[1]['icon']);
        self::assertSame('languages.confirm.disable', $actions[1]['confirm']);
        self::assertSame('languages.confirm.disable_title', $actions[1]['confirmTitle']);
        self::assertSame('secondary', $actions[2]['placement']);
        self::assertSame('mutation', $actions[2]['kind']);
        self::assertSame('star', $actions[2]['icon']);
        self::assertSame('languages.confirm.set_default', $actions[2]['confirm']);
    }

    public function testDisabledNonDefaultLanguageHasOnlySecondaryEditAndEnableMenuActions(): void
    {
        $actions = $this->rowActions(
            language: $this->language(enabled: false, isDefault: false),
            canManage: true,
            availableActions: ['enable', 'disable', 'set-default'],
        );

        self::assertSame(['edit', 'enable'], array_column($actions, 'key'));
        self::assertSame('secondary', $actions[0]['placement']);
        self::assertSame('secondary', $actions[1]['placement']);
        self::assertSame('mutation', $actions[1]['kind']);
        self::assertSame('check-circle', $actions[1]['icon']);
        self::assertSame('languages.confirm.enable', $actions[1]['confirm']);
        self::assertSame('languages.confirm.enable_title', $actions[1]['confirmTitle']);
    }

    public function testEditPermissionControlsBothModalMenuActionAndNameTrigger(): void
    {
        $row = $this->row(
            language: $this->language(enabled: true, isDefault: false),
            canManage: true,
            availableActions: [],
        );

        self::assertSame('edit', $row['actions'][0]['key']);
        self::assertSame('modal', $row['actions'][0]['kind']);
        self::assertSame('secondary', $row['actions'][0]['placement']);
        self::assertSame('/admin/api/modal/languages/15/edit', $row['cells']['name'][0]['modalUrl']);
        self::assertSame('medium', $row['cells']['name'][0]['modalSize']);

        $withoutEditPermission = $this->row(
            language: $this->language(enabled: true, isDefault: false),
            canManage: false,
            availableActions: [],
        );

        self::assertSame([], $withoutEditPermission['actions']);
        self::assertSame('flag', $withoutEditPermission['cells']['name'][0]['type']);
        self::assertArrayNotHasKey('modalUrl', $withoutEditPermission['cells']['name'][0]);
    }

    /**
     * @param array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int} $language
     * @param list<string> $availableActions
     * @return list<array<string, mixed>>
     */
    private function rowActions(array $language, bool $canManage, array $availableActions): array
    {
        return $this->row($language, $canManage, $availableActions)['actions'];
    }

    /**
     * @param array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int} $language
     * @param list<string> $availableActions
     * @return array<string, mixed>
     */
    private function row(array $language, bool $canManage, array $availableActions): array
    {
        $grid = new LanguagesDataGrid(
            languages: $this->languageModel(),
            translator: $this->translator(),
            authorization: $this->authorization($canManage),
            actionPresentation: $this->actionPresentation($availableActions),
            urls: $this->urls(),
        );
        $rowMethod = new ReflectionMethod($grid, 'row');
        $row = $rowMethod->invoke($grid, $language);
        self::assertInstanceOf(DataGridRowDefinition::class, $row);
        $serialized = $row->toArray();

        return $serialized;
    }

    /** @return array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int} */
    private function language(bool $enabled, bool $isDefault): array
    {
        return [
            'id' => 15,
            'code' => 'en',
            'name' => 'English',
            'flag_code' => 'GB',
            'enabled' => $enabled ? 1 : 0,
            'is_default' => $isDefault ? 1 : 0,
            'sort_order' => 1,
        ];
    }

    private function languageModel(): LanguageModel
    {
        return (new ReflectionClass(LanguageModel::class))->newInstanceWithoutConstructor();
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static function (string $key, array $parameters = []): string {
            return $key . ($parameters === [] ? '' : ':' . implode(',', $parameters));
        });

        return $translator;
    }

    private function authorization(bool $canManage): AuthorizationService
    {
        $principalProvider = $this->createMock(CurrentPrincipalProviderInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn($canManage ? [['id' => 1]] : []);
        if ($canManage) {
            $user = new AuthenticatedUser(7, 'admin@example.test');
            $principalProvider->method('currentUser')->willReturn($user);
            $principalProvider->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($user));
        } else {
            $principalProvider->method('currentUser')->willReturn(null);
            $principalProvider->method('currentPrincipal')->willReturn(null);
        }

        return new AuthorizationService(
            currentUser: $principalProvider,
            database: new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
        );
    }

    /** @param list<string> $availableActions */
    private function actionPresentation(array $availableActions): ModuleActionPresentationFactory
    {
        $presentation = $this->createMock(ModuleActionPresentationFactory::class);
        $presentation->method('rowAction')->willReturnCallback(
            static function (string $moduleCode, string $action, int $entityId, DataGridRowActionPlacement $placement) use ($availableActions): ?DataGridRowActionDefinition {
                if ($moduleCode !== 'system.languages' || !in_array($action, $availableActions, true)) {
                    return null;
                }

                $confirmation = match ($action) {
                    'enable' => new ConfirmationDefinition(
                        messageKey: 'languages.confirm.enable',
                        titleKey: 'languages.confirm.enable_title',
                    ),
                    'disable' => new ConfirmationDefinition(
                        messageKey: 'languages.confirm.disable',
                        titleKey: 'languages.confirm.disable_title',
                    ),
                    'set-default' => new ConfirmationDefinition('languages.confirm.set_default'),
                    default => throw new \LogicException('Unexpected action.'),
                };

                return (new DataGridRowActionDefinition(
                    key: $action,
                    label: $action,
                    url: '/admin/languages/ajax/' . $entityId,
                    method: 'POST',
                    confirmation: $confirmation,
                    kind: DataGridRowActionKind::Mutation,
                    placement: $placement,
                    refresh: true,
                ))->withIcon(DataGridRowActionIcon::forKey($action) ?? throw new \LogicException('Missing DataGrid action icon.'));
            },
        );

        return $presentation;
    }

    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->getNamed('admin.api.modal.edit', '/admin/api/modal/{module}/{id}/edit', ControllerAction::for('EditorModalController', 'edit'));

        return new UrlGenerator($router);
    }
}
