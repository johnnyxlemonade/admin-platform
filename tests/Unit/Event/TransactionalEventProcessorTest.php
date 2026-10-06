<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Event;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogService;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\DomainEventListenerInterface;
use Lemonade\Admin\Event\MissingAuditEventException;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class TransactionalEventProcessorTest extends TestCase
{
    public function testAuditIsWrittenBeforeCommitAndNotificationRunsAfterCommit(): void
    {
        $connection = new TransactionConnection(false);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new DomainEventDispatcher($logger);
        $listener = new class ($connection) implements DomainEventListenerInterface {
            public ?DomainEvent $event = null;

            public function __construct(private readonly TransactionConnection $connection) {}

            public function handle(DomainEvent $event): void
            {
                $this->event = $event;
                $this->connection->steps[] = 'notification';
            }
        };
        $dispatcher->listen($listener);
        $processor = $this->processor($connection, $dispatcher);

        $result = $processor->execute($this->operation(), function (TransactionalEventCollector $events) use ($connection): int {
            $connection->steps[] = 'mutation';
            $events->record(new DomainEvent('system.users.updated', 'system.users', 'user', '2'));

            return 42;
        });

        self::assertSame(42, $result);
        self::assertSame(['mutation', 'audit', 'commit', 'notification'], $connection->steps);
        self::assertNotNull($listener->event);
        $actor = $listener->event->auditActor();
        self::assertNotNull($actor);
        self::assertSame('user', $actor->type()->value);
        self::assertSame('user:1', $actor->key());
        self::assertSame(1, $listener->event->actorUserId());
    }

    public function testAuditFailureRollsBackAndDoesNotDispatchNotification(): void
    {
        $connection = new TransactionConnection(true);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new DomainEventDispatcher($logger);
        $dispatcher->listen(new class implements DomainEventListenerInterface {
            public function handle(DomainEvent $event): void
            {
                throw new RuntimeException('Notification must not run after rollback.');
            }
        });
        $processor = $this->processor($connection, $dispatcher);

        $this->expectException(RuntimeException::class);

        try {
            $processor->execute($this->operation(), function (TransactionalEventCollector $events) use ($connection): void {
                $connection->steps[] = 'mutation';
                $events->record(new DomainEvent('system.users.updated', 'system.users', 'user', '2'));
            });
        } finally {
            self::assertSame(['mutation', 'audit', 'rollback'], $connection->steps);
        }
    }

    public function testThrowingAuditWriterRollsBackWithoutCommitOrPostCommitDispatch(): void
    {
        $connection = new TransactionConnection(false);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new DomainEventDispatcher($logger);
        $dispatcher->listen(new class ($connection) implements DomainEventListenerInterface {
            public function __construct(private readonly TransactionConnection $connection) {}

            public function handle(DomainEvent $event): void
            {
                $this->connection->steps[] = 'notification';
            }
        });
        $writer = new class implements AuditLogWriterInterface {
            public function record(DomainEvent $event, AuditOperation $operation): void
            {
                throw new RuntimeException('Audit writer unavailable.');
            }
        };
        $processor = $this->processor($connection, $dispatcher, $writer);

        $this->expectExceptionMessage('Audit writer unavailable.');
        try {
            $processor->execute($this->operation(), function (TransactionalEventCollector $events) use ($connection): void {
                $connection->steps[] = 'mutation';
                $events->record(new DomainEvent('system.users.updated', 'system.users', 'user', '2'));
            });
        } finally {
            self::assertSame(['mutation', 'rollback'], $connection->steps);
        }
    }

    public function testNotificationFailureIsLoggedAfterCommittedAudit(): void
    {
        $connection = new TransactionConnection(false);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $dispatcher = new DomainEventDispatcher($logger);
        $dispatcher->listen(new class implements DomainEventListenerInterface {
            public function handle(DomainEvent $event): void
            {
                throw new RuntimeException('Notification persistence failed.');
            }
        });
        $processor = $this->processor($connection, $dispatcher);

        $result = $processor->execute($this->operation(), function (TransactionalEventCollector $events) use ($connection): string {
            $connection->steps[] = 'mutation';
            $events->record(new DomainEvent('system.users.updated', 'system.users', 'user', '2'));

            return 'saved';
        });

        self::assertSame('saved', $result);
        self::assertSame(['mutation', 'audit', 'commit'], $connection->steps);
    }

    public function testMissingAuditEventRollsBackMutation(): void
    {
        $connection = new TransactionConnection(false);
        $processor = $this->processor($connection, new DomainEventDispatcher($this->createMock(LoggerInterface::class)));

        $this->expectException(MissingAuditEventException::class);
        try {
            $processor->execute($this->operation(), function () use ($connection): void {
                $connection->steps[] = 'mutation';
            });
        } finally {
            self::assertSame(['mutation', 'rollback'], $connection->steps);
        }
    }

    private function processor(TransactionConnection $connection, DomainEventDispatcher $dispatcher, AuditLogWriterInterface|null $writer = null): TransactionalEventProcessor
    {
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));

        return new TransactionalEventProcessor($database, $writer ?? new AuditLogService($database), $dispatcher);
    }

    private function operation(): AuditOperation
    {
        return new AuditOperation('system.users', 'users.test', AuditActor::user(1));
    }
}

final class TransactionConnection implements ConnectionInterface
{
    /** @var list<string> */
    public array $steps = [];

    private bool $inTransaction = false;

    public function __construct(private readonly bool $failAudit) {}

    public function select(string $sql, array $bindings = []): array
    {
        return [];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $this->steps[] = 'audit';
        if ($this->failAudit) {
            throw new RuntimeException('Audit insert failed.');
        }

        return 1;
    }

    public function beginTransaction(): void
    {
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        $this->steps[] = 'commit';
        $this->inTransaction = false;
    }

    public function rollBack(): void
    {
        $this->steps[] = 'rollback';
        $this->inTransaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction) {
            return $callback($this);
        }

        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollBack();

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
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
