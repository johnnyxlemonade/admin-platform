<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor;

use PHPUnit\Framework\TestCase;

final class EditorLockContractTest extends TestCase
{
    public function testInMemoryOwnershipLifecycleHasOneCanonicalLock(): void
    {
        $locks = new InMemoryEditorLocks();

        // Classic create has no persisted resource, therefore never acquires a lock.
        self::assertSame(0, $locks->count());

        // AJAX create transitions to edit only after persistence and acquires one row.
        self::assertTrue($locks->acquire('system.users', '41', 'user:7'));
        self::assertSame(1, $locks->count());
        self::assertTrue($locks->acquire('system.users', '41', 'user:7'));
        self::assertSame(1, $locks->count());
        self::assertFalse($locks->acquire('system.users', '41', 'external:oidc:provider-a:sha256-other'));

        self::assertTrue($locks->acquire('system.users', '42', 'external:oidc:provider-a:sha256-current'));
        self::assertTrue($locks->acquire('system.users', '42', 'external:oidc:provider-a:sha256-current'));
        self::assertFalse($locks->acquire('system.users', '42', 'user:7'));
        self::assertSame(2, $locks->count());

        // AJAX save retains the same ownership row; classic save and navigation release it.
        self::assertTrue($locks->refresh('system.users', '41', 'user:7'));
        self::assertSame(2, $locks->count());
        self::assertFalse($locks->release('system.users', '41', 'external:oidc:provider-a:sha256-current'));
        self::assertTrue($locks->release('system.users', '41', 'user:7'));
        self::assertTrue($locks->release('system.users', '42', 'external:oidc:provider-a:sha256-current'));
        self::assertSame(0, $locks->count());
    }
}

final class InMemoryEditorLocks
{
    /** @var array<string, string> */
    private array $owners = [];

    public function acquire(string $resourceType, string $resourceId, string $ownerKey): bool
    {
        $key = $resourceType . ':' . $resourceId;
        if (isset($this->owners[$key]) && $this->owners[$key] !== $ownerKey) {
            return false;
        }
        $this->owners[$key] = $ownerKey;

        return true;
    }

    public function refresh(string $resourceType, string $resourceId, string $ownerKey): bool
    {
        return ($this->owners[$resourceType . ':' . $resourceId] ?? null) === $ownerKey;
    }

    public function release(string $resourceType, string $resourceId, string $ownerKey): bool
    {
        $key = $resourceType . ':' . $resourceId;
        if (($this->owners[$key] ?? null) !== $ownerKey) {
            return false;
        }
        unset($this->owners[$key]);

        return true;
    }

    public function count(): int
    {
        return count($this->owners);
    }
}
