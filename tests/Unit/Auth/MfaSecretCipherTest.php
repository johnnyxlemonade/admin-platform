<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MfaSecretCipherTest extends TestCase
{
    public function testItRoundTripsOnlyForTheOriginalUser(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
        $envelope = $cipher->encrypt('BASE32SECRET', 42);

        self::assertSame('BASE32SECRET', $cipher->decrypt($envelope, 42));
        $this->expectException(RuntimeException::class);
        $cipher->decrypt($envelope, 43);
    }

    public function testItRejectsMalformedUnsupportedAndTamperedEnvelopes(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
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
        $envelope = (new MfaSecretCipher(str_repeat('a', 32)))->encrypt('BASE32SECRET', 42);

        $this->expectException(RuntimeException::class);
        (new MfaSecretCipher(str_repeat('b', 32)))->decrypt($envelope, 42);
    }
}
