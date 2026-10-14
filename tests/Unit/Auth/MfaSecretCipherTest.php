<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MfaSecretCipherTest extends TestCase
{
    public function testItRoundTripsOnlyForTheOriginalUser(): void
    {
        $cipher = MfaSecretCipher::fromKey(str_repeat('a', 32));
        $envelope = $cipher->encrypt('BASE32SECRET', 42);

        self::assertSame('BASE32SECRET', $cipher->decrypt($envelope, 42));
        $this->expectException(RuntimeException::class);
        $cipher->decrypt($envelope, 43);
    }

    public function testItRejectsMalformedUnsupportedAndTamperedEnvelopes(): void
    {
        $cipher = MfaSecretCipher::fromKey(str_repeat('a', 32));
        $envelope = $cipher->encrypt('BASE32SECRET', 42);

        foreach (['v2.payload', 'v1.invalid', substr($envelope, 0, -1) . 'A'] as $invalidEnvelope) {
            try {
                $cipher->decrypt($invalidEnvelope, 42);
                self::fail('Expected invalid MFA envelope to be rejected.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testItRejectsAValidEnvelopeEncryptedWithAnotherKey(): void
    {
        $envelope = MfaSecretCipher::fromKey(str_repeat('a', 32))->encrypt('BASE32SECRET', 42);

        $this->expectException(RuntimeException::class);
        MfaSecretCipher::fromKey(str_repeat('b', 32))->decrypt($envelope, 42);
    }

    public function testDisabledCipherRejectsEncryptionAndDecryption(): void
    {
        $cipher = MfaSecretCipher::disabled();

        foreach (
            [
                static fn(): string => $cipher->encrypt('BASE32SECRET', 42),
                static fn(): string => $cipher->decrypt('v1.payload', 42),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('Expected disabled MFA cipher to reject cryptographic use.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
