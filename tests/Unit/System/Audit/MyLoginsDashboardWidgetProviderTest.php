<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Audit;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Dashboard\DashboardWidgetDefinition;
use Lemonade\Admin\System\Audit\Dashboard\MyLoginsDashboardWidgetProvider;
use Lemonade\Admin\System\Audit\PersonalLoginAuditReaderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class MyLoginsDashboardWidgetProviderTest extends TestCase
{
    public function testLocalUserSeesOnlyTheirOwnLoginEvents(): void
    {
        $reader = new MyLoginsFakeReader([
            ['actor_key' => 'user:42', 'created_at' => '2026-09-23 16:45:36', 'payload' => ['method' => 'local']],
            ['actor_key' => 'user:7', 'created_at' => '2026-09-23 15:00:00', 'payload' => ['method' => 'local']],
        ]);
        $provider = new MyLoginsDashboardWidgetProvider($reader, $this->translator());
        $context = DashboardWidgetContext::forLocalUser(new AuthenticatedUser(42, 'admin@example.test'), 'cs');

        $content = $provider->load($context, $this->definition($provider));

        self::assertSame('user:42', $reader->requestedActorKey);
        self::assertSame([[
            'createdAt' => '2026-09-23 16:45:36',
            'method' => 'Local',
            'provider' => null,
        ]], $content->viewData()['items']);
    }

    public function testOidcLoginUsesTheLocalUserAuditActorKey(): void
    {
        $context = DashboardWidgetContext::forLocalUser(new AuthenticatedUser(42, 'editor@example.test'), 'en');
        $reader = new MyLoginsFakeReader([
            ['actor_key' => $context->ownerKey(), 'created_at' => '2026-09-23 16:45:36', 'payload' => ['method' => 'oidc', 'provider' => 'provider-a']],
            ['actor_key' => 'user:7', 'created_at' => '2026-09-23 15:00:00', 'payload' => ['method' => 'oidc', 'provider' => 'provider-a']],
        ]);
        $provider = new MyLoginsDashboardWidgetProvider($reader, $this->translator());

        $content = $provider->load($context, $this->definition($provider));

        self::assertSame('user:42', $reader->requestedActorKey);
        self::assertSame([[
            'createdAt' => '2026-09-23 16:45:36',
            'method' => 'OIDC',
            'provider' => 'provider-a',
        ]], $content->viewData()['items']);
    }

    public function testDefinitionIsSelfServiceWithoutGlobalAuditPermission(): void
    {
        $definition = $this->definition(new MyLoginsDashboardWidgetProvider(new MyLoginsFakeReader([]), $this->translator()));

        self::assertNull($definition->access()->permissionCode());
        self::assertNull($definition->access()->permissionCode());
        self::assertSame('audit.widgets.my_logins.empty', $definition->presentation()->emptyMessageKey());
        self::assertSame('audit::widgets.my-logins', $definition->presentation()->contentView());
    }

    public function testOidcUserWithNoLoginEventsGetsEmptyState(): void
    {
        $provider = new MyLoginsDashboardWidgetProvider(new MyLoginsFakeReader([]), $this->translator());
        $context = DashboardWidgetContext::forLocalUser(new AuthenticatedUser(42, 'editor@example.test'), 'en');

        $content = $provider->load($context, $this->definition($provider));

        self::assertSame('empty', $content->state()->value);
        self::assertSame([], $content->viewData());
    }

    public function testMissingMethodAndProviderMetadataUsesSafeFallback(): void
    {
        $context = DashboardWidgetContext::forLocalUser(new AuthenticatedUser(42, 'editor@example.test'), 'en');
        $reader = new MyLoginsFakeReader([
            ['actor_key' => $context->ownerKey(), 'created_at' => '2026-09-23 16:45:36', 'payload' => []],
        ]);
        $provider = new MyLoginsDashboardWidgetProvider($reader, $this->translator());

        $content = $provider->load($context, $this->definition($provider));

        self::assertSame([[
            'createdAt' => '2026-09-23 16:45:36',
            'method' => 'Unknown',
            'provider' => null,
        ]], $content->viewData()['items']);
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(
            static fn(string $key): string => match ($key) {
                'audit.widgets.my_logins.methods.oidc' => 'OIDC',
                'audit.widgets.my_logins.methods.unknown' => 'Unknown',
                default => 'Local',
            },
        );

        return $translator;
    }

    private function definition(MyLoginsDashboardWidgetProvider $provider): DashboardWidgetDefinition
    {
        foreach ($provider->definitions() as $definition) {
            return $definition;
        }

        self::fail('My logins widget must provide a definition.');
    }
}

final class MyLoginsFakeReader implements PersonalLoginAuditReaderInterface
{
    public string $requestedActorKey = '';

    /** @param list<array{actor_key:string,created_at:string,payload:array<string,mixed>}> $events */
    public function __construct(private readonly array $events) {}

    public function recentLoginEvents(string $actorKey, int $limit): array
    {
        $this->requestedActorKey = $actorKey;

        return array_values(array_map(
            static fn(array $event): array => ['created_at' => $event['created_at'], 'payload' => $event['payload']],
            array_slice(array_filter($this->events, static fn(array $event): bool => $event['actor_key'] === $actorKey), 0, $limit),
        ));
    }
}
