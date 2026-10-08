<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Modules;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Cms\Routing\ModuleRoutePrefixPublicRepository;
use Lemonade\Admin\Cms\Routing\PublicModuleRuntimeCacheInvalidator;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleException;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Admin\Modules\Persistence\ModuleRoutePrefixModel;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Framework\Cache\CacheManager;
use Lemonade\Framework\Cache\Store\ArrayCacheItemPool;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ModuleStateResolverTest extends TestCase
{
    public function testItResolvesAvailableInstalledEnabledAndMissingStatesWithOneDatabaseRead(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new ModuleStateConnection([
            'test.optional' => false,
            'system.test' => false,
            'legacy.orphan' => true,
        ]);
        try {
            $resolver = new ModuleStateResolver($catalog, new ModuleModel($this->database($connection)));

            self::assertTrue($resolver->state('test.optional')->available());
            self::assertTrue($resolver->state('test.optional')->installed());
            self::assertFalse($resolver->state('test.optional')->enabled());
            self::assertTrue($resolver->state('system.test')->enabled());
            self::assertTrue($resolver->state('legacy.orphan')->missingCode());
            self::assertFalse($resolver->state('missing')->available());
            self::assertSame(1, $connection->selects);
        } finally {
            $cleanup();
        }
    }

    public function testItReusesAndInvalidatesThePersistentModuleStateSnapshot(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new ModuleStateConnection(['test.optional' => true]);
        try {
            $cache = new CacheManager(new ArrayCacheItemPool());
            $resolver = new ModuleStateResolver($catalog, new ModuleModel($this->database($connection)), $cache);

            self::assertTrue($resolver->state('test.optional')->enabled());
            $resolver->refresh();
            self::assertTrue($resolver->state('test.optional')->enabled());
            self::assertSame(1, $connection->selects);

            $resolver->forgetCachedDatabaseStates();
            self::assertTrue($resolver->state('test.optional')->enabled());
            self::assertSame(2, $connection->selects);
        } finally {
            $cleanup();
        }
    }

    public function testModuleMigrationIgnoresStalePersistentStateWithoutSuppressingRealAuditedMutations(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new ModuleStateConnection(['test.optional' => true]);
        $audits = new class implements AuditLogWriterInterface {
            /** @var list<string> */ public array $events = [];

            public function record(DomainEvent $event, AuditOperation $operation): void
            {
                $this->events[] = $event->code() . ':' . $operation->code();
            }
        };
        try {
            $cache = new CacheManager(new ArrayCacheItemPool());
            $database = $this->database($connection);
            $modules = new ModuleModel($database);
            $resolver = new ModuleStateResolver($catalog, $modules, $cache);
            $service = new ModuleLifecycleService(
                $resolver,
                $modules,
                new TransactionalEventProcessor($database, $audits, new DomainEventDispatcher(new NullLogger())),
            );

            self::assertTrue($resolver->state('test.optional')->installed());
            $connection->states = [];

            self::assertSame([], $service->migrateInstalled(AuditActor::migration('test')));
            self::assertSame([], $audits->events);

            $connection->states = ['test.optional' => false];
            $resolver->forgetCachedDatabaseStates();

            $service->enable('test.optional', AuditActor::system('test'));
            self::assertSame(['system.module_enabled:modules.enable'], $audits->events);
        } finally {
            $cleanup();
        }
    }

    public function testPublicModuleRuntimeInvalidatorClearsEveryModuleStateMutationEvent(): void
    {
        foreach (['system.module_installed', 'system.module_enabled', 'system.module_disabled'] as $eventCode) {
            [$catalog, $cleanup] = $this->catalog();
            $connection = new ModuleStateConnection(['test.optional' => true]);
            try {
                $cache = new CacheManager(new ArrayCacheItemPool());
                $database = $this->database($connection);
                $resolver = new ModuleStateResolver($catalog, new ModuleModel($database), $cache);
                $prefixes = new ModuleRoutePrefixPublicRepository(new ModuleRoutePrefixModel($database), $cache);
                $invalidator = new PublicModuleRuntimeCacheInvalidator($resolver, $prefixes);

                $resolver->state('test.optional');
                $invalidator->handle(new DomainEvent($eventCode, 'test.optional', 'system_module', 'test.optional'));
                $resolver->state('test.optional');

                self::assertSame(2, $connection->selects, $eventCode);
            } finally {
                $cleanup();
            }
        }
    }

    public function testPublicModuleRuntimeInvalidatorClearsTheRoutePrefixSnapshot(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new ModuleStateConnection(['test.optional' => true]);
        try {
            $cache = new CacheManager(new ArrayCacheItemPool());
            $database = $this->database($connection);
            $resolver = new ModuleStateResolver($catalog, new ModuleModel($database), $cache);
            $prefixes = new ModuleRoutePrefixPublicRepository(new ModuleRoutePrefixModel($database), $cache);
            $invalidator = new PublicModuleRuntimeCacheInvalidator($resolver, $prefixes);

            $prefixes->prefixFor('test.optional', 'cs');
            $prefixes->prefixFor('test.optional', 'cs');
            self::assertSame(1, $connection->selects);

            $invalidator->handle(new DomainEvent('system.module_route_prefix_synchronized', 'test.optional', 'system_module_route_prefix', 'test.optional:cs'));
            $prefixes->prefixFor('test.optional', 'cs');

            self::assertSame(2, $connection->selects);
        } finally {
            $cleanup();
        }
    }

    public function testLifecycleChangesOnlyInstalledOptionalModulesAndAuditsTheMutation(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new ModuleStateConnection(['test.optional' => false, 'system.test' => false]);
        $audits = new class implements AuditLogWriterInterface {
            /** @var list<string> */ public array $events = [];

            public function record(DomainEvent $event, AuditOperation $operation): void
            {
                $this->events[] = $event->code() . ':' . $operation->code();
            }
        };
        try {
            $database = $this->database($connection);
            $modules = new ModuleModel($database);
            $resolver = new ModuleStateResolver($catalog, $modules);
            $service = new ModuleLifecycleService($resolver, $modules, new TransactionalEventProcessor($database, $audits, new DomainEventDispatcher(new NullLogger())));

            self::assertTrue($service->enable('test.optional', AuditActor::system('test'))->enabled());
            self::assertTrue($connection->states['test.optional']);
            self::assertSame(['system.module_enabled:modules.enable'], $audits->events);
            $this->expectException(ModuleLifecycleException::class);
            $service->disable('system.test', AuditActor::system('test'));
        } finally {
            $cleanup();
        }
    }

    public function testLifecycleRollsBackEnabledStateWhenAuditWritingFails(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new ModuleStateConnection(['test.optional' => true]);
        $failingAudit = new class implements AuditLogWriterInterface {
            public function record(DomainEvent $event, AuditOperation $operation): void
            {
                throw new \RuntimeException('Audit writer failed.');
            }
        };
        try {
            $database = $this->database($connection);
            $modules = new ModuleModel($database);
            $service = new ModuleLifecycleService(
                new ModuleStateResolver($catalog, $modules),
                $modules,
                new TransactionalEventProcessor($database, $failingAudit, new DomainEventDispatcher(new NullLogger())),
            );

            try {
                $service->disable('test.optional', AuditActor::system('test'));
                self::fail('Expected audit writer failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Audit writer failed.', $exception->getMessage());
            }
            self::assertTrue($connection->states['test.optional']);
        } finally {
            $cleanup();
        }
    }

    /** @return array{ModuleCatalog, callable():void} */
    private function catalog(): array
    {
        $root = sys_get_temp_dir() . '/module-state-' . bin2hex(random_bytes(8));
        mkdir($root . '/storage/cache', 0775, true);
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);
        $catalog = new ModuleCatalog($context);
        file_put_contents($catalog->cachePath(), "<?php\nreturn ['" . OptionalManifestFixture::class . "', '" . SystemManifestFixture::class . "'];\n");

        return [$catalog, static function () use ($catalog, $root): void {
            @unlink($catalog->cachePath());
            @rmdir(dirname($catalog->cachePath()));
            @rmdir($root . '/storage');
            @rmdir($root);
        }];
    }

    private function database(ModuleStateConnection $connection): Database
    {
        return new Database($connection, $this->createMock(DatabaseDriverInterface::class));
    }
}

final class OptionalManifestFixture extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('test.optional', ModuleKind::Optional, FixtureProvider::class);
    }
}

final class SystemManifestFixture extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('system.test', ModuleKind::System, FixtureProvider::class);
    }
}

final class FixtureProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
    }
}

final class ModuleStateConnection implements ConnectionInterface
{
    public int $selects = 0;

    /** @param array<string, bool> $states */
    public function __construct(public array $states) {}

    public function select(string $sql, array $bindings = []): array
    {
        $this->selects++;
        return array_map(static fn(string $code, bool $enabled): array => ['code' => $code, 'enabled' => $enabled ? 1 : 0], array_keys($this->states), $this->states);
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $this->states[(string) $bindings[1]] = (int) $bindings[0] === 1;
        return 1;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        $states = $this->states;
        try {
            return $callback($this);
        } catch (\Throwable $exception) {
            $this->states = $states;

            throw $exception;
        }
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return '';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
