<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\AuthorizationTargetPolicy;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionIcon;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\Presentation\AdminThumbnailComponent;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Admin\System\Users\Actions\UsersActionRegistrar;
use Lemonade\Admin\System\Users\Actions\UsersActivateAction;
use Lemonade\Admin\System\Users\Actions\UsersDeactivateAction;
use Lemonade\Admin\System\Users\Actions\UsersDeleteAction;
use Lemonade\Admin\System\Users\Actions\UsersEditorCreateAction;
use Lemonade\Admin\System\Users\Actions\UsersEditorSaveAction;
use Lemonade\Admin\System\Users\Actions\UsersMfaResetAction;
use Lemonade\Admin\System\Users\Actions\UsersPermissionPreviewAction;
use Lemonade\Admin\System\Users\Actions\UsersRestoreAction;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGridQuery;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

/**
 * Overuje MFA presentation Users DataGridu a oddelene reset action preklady
 */
final class UsersMfaDataGridPresentationTest extends TestCase
{
    /**
     * Overuje compact status bunku pro aktivni i nenastavene MFA
     */
    public function testItRendersActiveAndInactiveMfaAsStatusCells(): void
    {
        $activeMfa = $this->row($this->user(mfaActive: true, mfaResetEligible: false));
        $inactiveMfa = $this->row($this->user(mfaActive: false, mfaResetEligible: false));

        self::assertSame([
            'type' => 'status',
            'value' => 'users.status.mfa_active',
            'class' => 'status-success',
            'translationKey' => 'users.status.mfa_active',
        ], $activeMfa['cells']['mfa'][0]);
        self::assertSame([
            'type' => 'status',
            'value' => 'users.status.mfa_inactive',
            'class' => 'status-muted',
            'translationKey' => 'users.status.mfa_inactive',
        ], $inactiveMfa['cells']['mfa'][0]);
    }

    /**
     * Overuje reset action s canonical labelem, potvrzenim a reset ikonou
     */
    public function testItShowsTheMfaResetActionOnlyForAnEligibleRow(): void
    {
        $row = $this->row($this->user(mfaActive: true, mfaResetEligible: true));

        self::assertSame(['mfa-reset'], array_column($row['actions'], 'key'));
        self::assertSame('users.actions.mfa_reset', $row['actions'][0]['label']);
        self::assertSame('users.confirm.mfa_reset', $row['actions'][0]['confirm']);
        self::assertSame('arrow-counterclockwise', $row['actions'][0]['icon']);
    }

    /**
     * Overuje, ze chybejici MFA, permission nebo target policy reset action skryji
     */
    public function testItHidesTheMfaResetActionWithoutMfaPermissionOrTargetAccess(): void
    {
        self::assertFalse($this->mfaResetEligible(mfaActive: false, actorPermissions: ['system.users.mfa_reset'], targetPermissions: ['system.users.mfa_reset']));
        self::assertFalse($this->mfaResetEligible(mfaActive: true, actorPermissions: [], targetPermissions: []));
        self::assertFalse($this->mfaResetEligible(mfaActive: true, actorPermissions: ['system.users.mfa_reset'], targetPermissions: ['system.users.edit']));
        self::assertSame([], $this->row($this->user(mfaActive: true, mfaResetEligible: false))['actions']);
    }

    /**
     * Overuje oddeleni action labelu, potvrzeni a uspesne toast zpravy
     */
    public function testItSeparatesMfaResetActionConfirmationAndSuccessTranslations(): void
    {
        $registry = new ModuleActionRegistry();
        (new UsersActionRegistrar(
            actions: $registry,
            save: $this->uninitialized(UsersEditorSaveAction::class),
            create: $this->uninitialized(UsersEditorCreateAction::class),
            permissionsPreview: $this->uninitialized(UsersPermissionPreviewAction::class),
            activate: $this->uninitialized(UsersActivateAction::class),
            deactivate: $this->uninitialized(UsersDeactivateAction::class),
            delete: $this->uninitialized(UsersDeleteAction::class),
            restore: $this->uninitialized(UsersRestoreAction::class),
            mfaReset: $this->uninitialized(UsersMfaResetAction::class),
        ))->register();
        $definition = $registry->action('system.users', 'mfa-reset')['definition'];
        $czech = require dirname(__DIR__, 4) . '/src/System/Users/Resources/lang/cs/users.php';

        self::assertSame('users.actions.mfa_reset', $definition->labelKey());
        self::assertSame('users.confirm.mfa_reset', $definition->confirmation()?->messageKey());
        self::assertSame('Resetovat dvoufázové ověření', $czech['actions']['mfa_reset']);
        self::assertSame('Opravdu chcete resetovat dvoufázové ověření tohoto uživatele?', $czech['confirm']['mfa_reset']);
        self::assertSame('Dvoufázové ověření bylo resetováno.', $czech['actions']['mfa_reset_success']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function row(array $user): array
    {
        $grid = new UsersDataGrid(
            users: $this->uninitialized(UsersDataGridQuery::class),
            userService: $this->uninitialized(UserService::class),
            translator: $this->translator(),
            authorization: $this->authorizationWithoutPermissions(),
            actionPresentation: $this->actionPresentation(),
            urls: new UrlGenerator(new Router()),
            files: $this->uninitialized(AdminFileModel::class),
            thumbnails: $this->uninitialized(AdminThumbnailComponent::class),
        );
        $rows = (new ReflectionMethod($grid, 'rows'))->invoke($grid, [$user], []);

        self::assertCount(1, $rows);
        self::assertInstanceOf(DataGridRowDefinition::class, $rows[0]);

        return $rows[0]->toArray();
    }

    /**
     * @param list<string> $actorPermissions
     * @param list<string> $targetPermissions
     */
    private function mfaResetEligible(bool $mfaActive, array $actorPermissions, array $targetPermissions): bool
    {
        $actor = new AuthenticatedUser(7, 'actor@example.test');
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn($actor);
        $authorization = new AuthorizationService(
            currentUser: $principals,
            database: new Database($this->createMock(ConnectionInterface::class), $this->createMock(DatabaseDriverInterface::class)),
        );
        $this->set($authorization, 'superAdminUsers', [7 => false, 15 => false]);
        $this->set($authorization, 'effectiveUserPermissions', [
            7 => $this->permissionDetails($actorPermissions),
            15 => $this->permissionDetails($targetPermissions),
        ]);
        $query = new UsersDataGridQuery(
            users: $this->uninitialized(UserModel::class),
            currentPrincipal: $principals,
            authorization: $authorization,
            targets: new AuthorizationTargetPolicy($authorization),
        );
        $eligibility = $query->rowActionEligibility([$this->user(mfaActive: $mfaActive, mfaResetEligible: false)]);

        return $eligibility[15]['mfa_reset'];
    }

    /**
     * @return array<string, mixed>
     */
    private function user(bool $mfaActive, bool $mfaResetEligible): array
    {
        return [
            'id' => 15,
            'email' => 'target@example.test',
            'active' => 1,
            'deleted_at' => null,
            'last_login_at' => null,
            'roles' => '',
            'is_super_admin' => 0,
            'mfa_active' => $mfaActive ? 1 : 0,
            'action_eligibility' => [
                'deactivate' => false,
                'delete' => false,
                'mfa_reset' => $mfaResetEligible,
            ],
        ];
    }

    /**
     * @param list<string> $permissions
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    private function permissionDetails(array $permissions): array
    {
        return array_map(
            static fn(string $permission): array => [
                'code' => $permission,
                'name_key' => '',
                'module_code' => 'system.users',
            ],
            $permissions,
        );
    }

    /**
     * Vytvari prekladac vracejici canonical translation key pro presentation kontrakt
     */
    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);

        return $translator;
    }

    /**
     * Vytvari authorization bez edit permission, aby test pokryl pouze MFA action
     */
    private function authorizationWithoutPermissions(): AuthorizationService
    {
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn(null);

        return new AuthorizationService(
            currentUser: $principals,
            database: new Database($this->createMock(ConnectionInterface::class), $this->createMock(DatabaseDriverInterface::class)),
        );
    }

    /**
     * Vytvari presentation action pouze pro MFA reset
     */
    private function actionPresentation(): ModuleActionPresentationFactory
    {
        $presentation = $this->createMock(ModuleActionPresentationFactory::class);
        $presentation->method('rowAction')->willReturnCallback(static function (string $moduleCode, string $action, int $entityId): ?DataGridRowActionDefinition {
            if ($moduleCode !== 'system.users' || $action !== 'mfa-reset') {
                return null;
            }

            return (new DataGridRowActionDefinition(
                key: $action,
                label: 'users.actions.mfa_reset',
                url: '/admin/system/users/ajax/' . $entityId,
                method: 'POST',
                confirmation: new ConfirmationDefinition('users.confirm.mfa_reset'),
                kind: DataGridRowActionKind::Mutation,
                placement: DataGridRowActionPlacement::Secondary,
                refresh: true,
            ))->withIcon(DataGridRowActionIcon::forKey($action) ?? throw new \LogicException('Missing MFA reset icon.'));
        });

        return $presentation;
    }

    /**
     * Vytvari objekt bez konstruktoru pro zavislost, kterou testovane chovani nepouziva
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function uninitialized(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * Nastavi testovaci cache authorization service bez databazoveho dotazu
     */
    private function set(object $object, string $property, mixed $value): void
    {
        (new ReflectionObject($object))->getProperty($property)->setValue($object, $value);
    }
}
