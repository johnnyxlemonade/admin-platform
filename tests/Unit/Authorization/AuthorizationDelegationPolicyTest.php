<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Authorization;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationResolverInterface;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use PHPUnit\Framework\TestCase;

final class AuthorizationDelegationPolicyTest extends TestCase
{
    public function testSuperAdminCanDelegateAnyKnownPermissionAndAssignAnyExistingRole(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(true);
        $resolver->method('roleExists')->with(7)->willReturn(true);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.users.disable', 'system.users', 'users.permissions.disable'));
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));
        $actor = new AuthenticatedUser(1, 'root@example.test');

        self::assertTrue($policy->canDelegatePermissions($actor, ['system.users.disable']));
        self::assertTrue($policy->canAssignRole($actor, 7));
    }

    public function testNonSuperAdminCanDelegateOnlyAPermissionSubset(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.users.view', 'system.users.edit']);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));
        $catalog->register(new PermissionDefinition('system.users.edit', 'system.users', 'users.permissions.edit'));
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));
        $actor = new AuthenticatedUser(2, 'admin@example.test');

        self::assertTrue($policy->canDelegatePermissions($actor, ['system.users.view']));
        self::assertFalse($policy->canDelegatePermissions($actor, ['system.users.disable']));
    }

    public function testDelegationRejectsUnknownPermissionEvenForSuperAdmin(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $catalog = new PermissionCatalogRegistry();
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));

        self::assertFalse($policy->canDelegatePermissions(new AuthenticatedUser(1, 'root@example.test'), ['unknown.permission']));
    }

    public function testNonSuperAdminCannotAssignSuperAdminOrBroaderRole(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('roleExists')->willReturn(true);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.users.view']);
        $resolver->method('isSuperAdminRole')->willReturnMap([[10, true], [11, false], [12, false]]);
        $resolver->method('effectivePermissionsForRole')->willReturnMap([[11, ['system.users.view', 'system.users.edit']], [12, ['system.users.view']]]);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));
        $catalog->register(new PermissionDefinition('system.users.edit', 'system.users', 'users.permissions.edit'));
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));
        $actor = new AuthenticatedUser(2, 'admin@example.test');

        self::assertFalse($policy->canAssignRole($actor, 10));
        self::assertFalse($policy->canAssignRole($actor, 11));
        self::assertTrue($policy->canAssignRole($actor, 12));
    }

    public function testOnlySuperAdminCanDelegateASuperAdminOnlyPermission(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.audit.view']);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.audit.view', 'system.audit', 'audit.permissions.view', PermissionDelegation::SuperAdminOnly));
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));

        self::assertFalse($policy->canDelegatePermissions(new AuthenticatedUser(2, 'admin@example.test'), ['system.audit.view']));
    }

    public function testAUserDeniedAnEffectiveDeletePermissionCannotDelegateItOrAssignARoleContainingIt(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.users.view']);
        $resolver->method('roleExists')->with(12)->willReturn(true);
        $resolver->method('isSuperAdminRole')->with(12)->willReturn(false);
        $resolver->method('effectivePermissionsForRole')->with(12)->willReturn(['system.users.view', 'system.users.delete']);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));
        $catalog->register(new PermissionDefinition('system.users.delete', 'system.users', 'users.permissions.delete'));
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));
        $actor = new AuthenticatedUser(2, 'admin@example.test');

        self::assertFalse($policy->canDelegatePermissions($actor, ['system.users.delete']));
        self::assertFalse($policy->canAssignRole($actor, 12));
    }

    public function testDelegationValidatesTheWholeKnownSetAndItsDependencies(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.users.view', 'system.users.edit']);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(
            new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'),
            new PermissionDefinition('system.users.edit', 'system.users', 'users.permissions.edit', requires: ['system.users.view']),
        );
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));
        $actor = new AuthenticatedUser(2, 'admin@example.test');

        self::assertSame(
            ['system.users.view', 'system.users.edit'],
            $policy->delegablePermissionCodes($actor, ['system.users.view', 'system.users.edit', 'unknown.permission']),
        );
        self::assertTrue($policy->canDelegatePermissions($actor, ['system.users.view', 'system.users.edit']));
        self::assertFalse($policy->canDelegatePermissions($actor, ['system.users.edit', 'unknown.permission']));
    }

    public function testDependencyMustAlsoBePartOfTheActorsEffectiveAuthority(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.users.edit']);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(
            new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'),
            new PermissionDefinition('system.users.edit', 'system.users', 'users.permissions.edit', requires: ['system.users.view']),
        );
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));

        self::assertFalse($policy->canDelegatePermissions(new AuthenticatedUser(2, 'admin@example.test'), ['system.users.edit']));
    }

    public function testLoadedRoleFilteringUsesBatchPermissionStateWithoutRoleExistenceChecks(): void
    {
        $resolver = $this->createMock(AuthorizationResolverInterface::class);
        $resolver->expects(self::never())->method('roleExists');
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('effectivePermissionsForUser')->willReturn(['system.users.view']);
        $resolver->expects(self::once())->method('permissionCodesForRoles')->with([10, 11, 12])->willReturn([
            10 => [],
            11 => ['system.users.view', 'system.users.edit'],
            12 => ['system.users.view'],
        ]);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(
            new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'),
            new PermissionDefinition('system.users.edit', 'system.users', 'users.permissions.edit'),
        );
        $policy = new AuthorizationDelegationPolicy($resolver, $catalog, new PermissionDependencyResolver($catalog));

        self::assertSame([12], $policy->assignableLoadedRoleIds(new AuthenticatedUser(2, 'admin@example.test'), [
            ['id' => 10, 'is_super_admin' => 1],
            ['id' => 11, 'is_super_admin' => 0],
            ['id' => 12, 'is_super_admin' => 0],
        ]));
    }
}
