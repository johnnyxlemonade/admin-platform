<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Event;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\DomainEventListenerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class DomainEventTest extends TestCase
{
    public function testPayloadExcludesCredentialAndTransportValues(): void
    {
        $event = new DomainEvent('system.users.updated', 'system.users', 'user', '4', [
            'user' => 'Ada Lovelace',
            'password' => 'plain-text',
            'password_hash' => 'hash',
            'LEMONADE_CSRF' => 'csrf',
            'session_id' => 'session',
            'nested' => ['editor_lock_token' => 'lock', 'field' => 'phone'],
        ]);

        self::assertSame([
            'user' => 'Ada Lovelace',
            'nested' => ['field' => 'phone'],
        ], $event->payload());
    }

    public function testPayloadPreservesListValues(): void
    {
        $event = new DomainEvent('system.users.updated', 'system.users', 'user', '4', [
            'fields' => ['first_name', 'email'],
        ]);

        self::assertSame(['fields' => ['first_name', 'email']], $event->payload());
    }

    public function testListenerFailureDoesNotChangeAnAlreadyCommittedOperationResult(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $dispatcher = new DomainEventDispatcher($logger);
        $dispatcher->listen(new class implements DomainEventListenerInterface {
            public function handle(DomainEvent $event): void
            {
                throw new RuntimeException('Secondary persistence is unavailable.');
            }
        });

        $dispatcher->dispatch(new DomainEvent('system.users.updated', 'system.users', 'user', '4'));

        self::addToAssertionCount(1);
    }
}
