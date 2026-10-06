<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Notification;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Notification\Contract\NotificationInboxRepositoryInterface;
use Lemonade\Admin\Notification\NotificationPresentation;
use Lemonade\Admin\Notification\NotificationRecipientIdentity;
use Lemonade\Admin\Notification\NotificationRecipientIdentityResolver;
use Lemonade\Admin\Notification\NotificationService;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testLocalDirectAudienceIsVisibleOnlyToItsTarget(): void
    {
        $repository = new FakeInboxRepository([['id' => 1, 'directUserId' => 7]]);
        $service = $this->service($repository, NotificationRecipientIdentity::local(new AuthenticatedUser(7, 'local@example.test'), []));

        self::assertSame([1], array_column($service->inbox()['items'], 'id'));
        self::assertSame([], $this->service($repository, NotificationRecipientIdentity::local(new AuthenticatedUser(8, 'other@example.test'), []))->inbox()['items']);
    }

    public function testRoleAudienceIsVisibleToRoleMemberButNotDirectOtherUser(): void
    {
        $repository = new FakeInboxRepository([
            ['id' => 1, 'role' => 'editor'],
            ['id' => 2, 'directUserId' => 7],
        ]);
        $local = NotificationRecipientIdentity::local(new AuthenticatedUser(7, 'local@example.test'), ['editor']);
        $otherUser = NotificationRecipientIdentity::local(new AuthenticatedUser(8, 'other@example.test'), ['editor']);

        self::assertSame([1, 2], array_column($this->service($repository, $local)->inbox()['items'], 'id'));
        self::assertSame([1], array_column($this->service($repository, $otherUser)->inbox()['items'], 'id'));
    }

    public function testMarkReadUsesCanonicalLocalRecipientKeys(): void
    {
        $local = NotificationRecipientIdentity::local(new AuthenticatedUser(7, 'local@example.test'), []);
        $otherUser = NotificationRecipientIdentity::local(new AuthenticatedUser(8, 'other@example.test'), ['editor']);
        $repository = new FakeInboxRepository([['id' => 1, 'role' => 'editor'], ['id' => 2, 'directUserId' => 7]]);

        self::assertTrue($this->service($repository, $local)->markRead(2));
        self::assertTrue($this->service($repository, $otherUser)->markRead(1));
        self::assertSame('user:7', $repository->readKeys[2]);
        self::assertSame('user:8', $repository->readKeys[1]);
        self::assertSame(8, $otherUser->localUserId());
    }

    public function testAbsentReceiptIsUnreadAndInboxAndTopbarUseTheSameServiceContract(): void
    {
        $recipient = NotificationRecipientIdentity::local(new AuthenticatedUser(8, 'other@example.test'), ['editor']);
        $repository = new FakeInboxRepository([['id' => 1, 'role' => 'editor']]);
        $service = $this->service($repository, $recipient);

        self::assertSame(1, $service->inbox()['unread']);
        $service->markRead(1);
        self::assertSame(0, $service->inbox()['unread']);
        self::assertSame($recipient->key(), $repository->lastInboxRecipientKey);
        self::assertSame($recipient->key(), $repository->lastUnreadRecipientKey);
    }

    public function testUnreadIndicatorUsesTheCanonicalRecipientVisibility(): void
    {
        $recipient = NotificationRecipientIdentity::local(new AuthenticatedUser(8, 'other@example.test'), ['editor']);
        $repository = new FakeInboxRepository([['id' => 1, 'role' => 'editor']]);
        $service = $this->service($repository, $recipient);

        self::assertTrue($service->hasUnread());
        self::assertSame($recipient->key(), $repository->lastHasUnreadRecipientKey);

        $service->markRead(1);

        self::assertFalse($service->hasUnread());
    }

    public function testUnreadIndicatorIsFalseWithoutCurrentRecipient(): void
    {
        $service = $this->service(new FakeInboxRepository([['id' => 1, 'directUserId' => 7]]), null);

        self::assertFalse($service->hasUnread());
    }

    private function service(FakeInboxRepository $repository, ?NotificationRecipientIdentity $recipient): NotificationService
    {
        $resolver = $this->createMock(NotificationRecipientIdentityResolver::class);
        $resolver->method('current')->willReturn($recipient);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturn('Unknown user');
        return new NotificationService($repository, $resolver, $translator, new NotificationPresentation());
    }
}

final class FakeInboxRepository implements NotificationInboxRepositoryInterface
{
    /** @var list<array{id:int,role?:string,directUserId?:int}> */
    private array $notifications;
    /** @var array<int, string> */
    public array $readKeys = [];
    public ?string $lastInboxRecipientKey = null;
    public ?string $lastUnreadRecipientKey = null;
    public ?string $lastHasUnreadRecipientKey = null;

    /** @param list<array{id:int,role?:string,directUserId?:int}> $notifications */
    public function __construct(array $notifications)
    {
        $this->notifications = $notifications;
    }

    public function inboxPageForRecipient(NotificationRecipientIdentity $recipient, int $page, int $perPage): array
    {
        $this->lastInboxRecipientKey = $recipient->key();
        return ['rows' => $this->rows($recipient), 'hasMore' => false];
    }

    public function unreadCountForRecipient(NotificationRecipientIdentity $recipient): int
    {
        $this->lastUnreadRecipientKey = $recipient->key();
        return count(array_filter($this->rows($recipient), fn(array $row): bool => $row['read_at'] === null));
    }

    public function hasUnreadForRecipient(NotificationRecipientIdentity $recipient): bool
    {
        $this->lastHasUnreadRecipientKey = $recipient->key();

        return $this->unreadCountForRecipient($recipient) > 0;
    }

    public function markReadForRecipient(int $notificationId, NotificationRecipientIdentity $recipient): bool
    {
        foreach ($this->rows($recipient) as $row) {
            if ($row['id'] === $notificationId) {
                $this->readKeys[$notificationId] = $recipient->key();
                return true;
            }
        }
        return false;
    }

    public function markAllReadForRecipient(NotificationRecipientIdentity $recipient): int
    {
        $rows = $this->rows($recipient);
        foreach ($rows as $row) {
            $this->readKeys[$row['id']] = $recipient->key();
        }
        return count($rows);
    }

    /** @return list<array{id:int,type:string,title:string,message:string,created_at:string,read_at:?string,author_first_name:?string,author_last_name:?string,author_email:?string}> */
    private function rows(NotificationRecipientIdentity $recipient): array
    {
        $rows = [];
        foreach ($this->notifications as $notification) {
            $visible = isset($notification['role']) && in_array($notification['role'], $recipient->roleCodes(), true)
                || isset($notification['directUserId']) && $notification['directUserId'] === $recipient->localUserId();
            if ($visible) {
                $rows[] = ['id' => $notification['id'], 'type' => 'info', 'title' => 'Title', 'message' => 'Message', 'created_at' => '2026-01-01 00:00:00', 'read_at' => ($this->readKeys[$notification['id']] ?? null) === $recipient->key() ? '2026-01-01 01:00:00' : null, 'author_first_name' => null, 'author_last_name' => null, 'author_email' => 'author@example.test'];
            }
        }
        return $rows;
    }
}
