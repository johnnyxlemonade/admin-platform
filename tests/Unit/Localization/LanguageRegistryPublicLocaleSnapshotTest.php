<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Localization;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Admin\Localization\PublicLocaleSnapshotCacheInvalidator;
use Lemonade\Framework\Cache\CacheManager;
use Lemonade\Framework\Cache\Store\ArrayCacheItemPool;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje persistentni snapshot jazyku pro public CMS runtime
 */
final class LanguageRegistryPublicLocaleSnapshotTest extends TestCase
{
    /**
     * Overuje mapovani defaultniho, aktivniho a disabled locale z jednoho snapshotu
     */
    public function testMapsPublicLocaleSnapshotFromOneDatabaseRead(): void
    {
        $reads = 0;
        $registry = $this->registry($reads);

        $snapshot = $registry->publicLocaleSnapshot();

        self::assertSame('cs', $snapshot->defaultLocale());
        self::assertTrue($snapshot->isEnabledNonDefault('en'));
        self::assertFalse($snapshot->isEnabledNonDefault('cs'));
        self::assertTrue($snapshot->isKnownLocale('de'));
        self::assertSame(1, $reads);
    }

    /**
     * Overuje, ze warm cache znovu nectou system_language
     */
    public function testReusesThePersistentPublicLocaleSnapshot(): void
    {
        $reads = 0;
        $registry = $this->registry($reads);

        $registry->publicLocaleSnapshot();
        $registry->publicLocaleSnapshot();

        self::assertSame(1, $reads);
    }

    /**
     * Overuje invalidaci snapshotu pro vsechny udalosti menici public locale stav
     */
    public function testInvalidatesTheSnapshotForEveryLanguageMutationEvent(): void
    {
        foreach ([
            'system.languages.created',
            'system.languages.updated',
            'system.languages.enabled',
            'system.languages.disabled',
            'system.languages.default_changed',
        ] as $eventCode) {
            $reads = 0;
            $registry = $this->registry($reads);
            $invalidator = new PublicLocaleSnapshotCacheInvalidator($registry);

            $registry->publicLocaleSnapshot();
            $invalidator->handle(new DomainEvent($eventCode, 'system.languages', 'language', '1'));
            $registry->publicLocaleSnapshot();

            self::assertSame(2, $reads, $eventCode);
        }
    }

    /**
     * Vytvori registry s pametovou framework cache a citadlem databazovych cteni
     */
    private function registry(int &$reads): LanguageRegistry
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(function (string $sql) use (&$reads): array {
            ++$reads;
            self::assertSame('SELECT code, enabled, is_default FROM system_language', $sql);

            return [
                ['code' => 'cs', 'enabled' => 1, 'is_default' => 1],
                ['code' => 'en', 'enabled' => 1, 'is_default' => 0],
                ['code' => 'de', 'enabled' => 0, 'is_default' => 0],
            ];
        });

        return new LanguageRegistry(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            new CacheManager(new ArrayCacheItemPool()),
        );
    }
}
