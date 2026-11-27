<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use Lemonade\Admin\Module\AdminModuleContributionLoader;
use PHPUnit\Framework\TestCase;

/**
 * Verifies one-shot contributions and terminal failure without replaying mutations
 */
final class AdminModuleContributionLoaderTest extends TestCase
{
    /**
     * Successful callbacks run once even when loading is requested again
     */
    public function testLoadsSuccessfulCallbacksOnce(): void
    {
        $loader = new AdminModuleContributionLoader();
        $calls = 0;
        $loader->defer(static function () use (&$calls): void {
            $calls++;
        });
        $loader->defer(static function () use ($loader): void {
            $loader->load();
        });
        $loader->load();
        $loader->load();
        self::assertSame(1, $calls);
    }

    /**
     * A failed materialization also closes the contribution boundary
     */
    public function testRejectsDeferAfterFailure(): void
    {
        $loader = new AdminModuleContributionLoader();
        $failure = new \RuntimeException('Failed registration');
        $loader->defer(static function () use ($failure): void {
            throw $failure;
        });
        try {
            $loader->load();
            self::fail('Failed loader returned successfully');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
        $this->expectException(\LogicException::class);
        $loader->defer(static function (): void {});
    }

    /**
     * A partially executed callback is never retried and the same Throwable is retained
     */
    public function testRetainsOriginalFailureWithoutRetry(): void
    {
        $loader = new AdminModuleContributionLoader();
        $failure = new \Error('Partial registration failed');
        $calls = 0;
        $loader->defer(static function () use (&$calls, $failure): void {
            $calls++;
            throw $failure;
        });
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $loader->load();
                self::fail('Failed loader returned successfully');
            } catch (\Error $actual) {
                self::assertSame($failure, $actual);
            }
        }
        self::assertSame(1, $calls);
    }

    /**
     * A later failure does not replay earlier successful registrations
     */
    public function testDoesNotReplaySuccessfulCallbacksBeforeFailure(): void
    {
        $loader = new AdminModuleContributionLoader();
        $calls = [];
        $failure = new \RuntimeException('Second registration failed');
        $loader->defer(static function () use (&$calls): void {
            $calls[] = 'first';
        });
        $loader->defer(static function () use (&$calls, $failure): void {
            $calls[] = 'second';
            throw $failure;
        });
        $loader->defer(static function () use (&$calls): void {
            $calls[] = 'third';
        });
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $loader->load();
                self::fail('Failed loader returned successfully');
            } catch (\RuntimeException $actual) {
                self::assertSame($failure, $actual);
            }
        }
        self::assertSame(['first', 'second'], $calls);
    }

    /**
     * Callbacks cannot extend the queue while it is being materialized
     */
    public function testRejectsDeferDuringLoading(): void
    {
        $loader = new AdminModuleContributionLoader();
        $loader->defer(static function () use ($loader): void {
            $loader->defer(static function (): void {});
        });
        $this->expectException(\LogicException::class);
        $loader->load();
    }

    /**
     * Successful materialization closes the contribution boundary
     */
    public function testRejectsDeferAfterLoading(): void
    {
        $loader = new AdminModuleContributionLoader();
        $loader->load();
        $this->expectException(\LogicException::class);
        $loader->defer(static function (): void {});
    }
}
