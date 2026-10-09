<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Lemonade\Admin\Auth\Oidc\OidcIdTokenVerifier;
use Lemonade\Admin\Auth\Oidc\OidcProtocolException;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;
use PHPUnit\Framework\TestCase;

final class OidcIdTokenVerifierTest extends TestCase
{
    public function testItReturnsOnlyVerifiedIdentityClaims(): void
    {
        $key = $this->key('current');
        $verifier = new OidcIdTokenVerifier($this->jwks($key['jwk']));
        $identity = $verifier->verify($this->token($key, $this->claims()), $this->configuration(), $this->metadata(), 'expected-nonce', 1000);

        self::assertSame('subject-1', $identity->subject());
        self::assertSame('identity@example.test', $identity->email());
        self::assertSame(['workspace'], $identity->groups());
    }

    public function testItRejectsInvalidClaims(): void
    {
        $key = $this->key('current');
        $verifier = new OidcIdTokenVerifier($this->jwks($key['jwk']));

        foreach ([
            ['iss' => 'https://unexpected.example.test', 'expected' => 'id_token_issuer_invalid'],
            ['aud' => 'other-client', 'expected' => 'id_token_audience_invalid'],
            ['aud' => ['client-id', 'other-client'], 'azp' => 'other-client', 'expected' => 'id_token_authorized_party_invalid'],
            ['exp' => 900, 'expected' => 'id_token_expired'],
            ['nbf' => 1061, 'expected' => 'id_token_not_active'],
            ['iat' => 1061, 'expected' => 'id_token_issued_at_invalid'],
            ['iat' => -85401, 'expected' => 'id_token_issued_at_invalid'],
            ['nonce' => 'other-nonce', 'expected' => 'id_token_nonce_invalid'],
        ] as $case) {
            $claims = array_replace($this->claims(), $case);
            $this->assertVerificationError($verifier, $this->token($key, $claims), $case['expected']);
        }
    }

    public function testItRejectsUnsupportedAlgorithmBeforeSignatureVerification(): void
    {
        $key = $this->key('current');
        $token = $this->token($key, $this->claims());
        [, $payload, $signature] = explode('.', $token);
        $unsupported = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'kid' => 'current'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=') . '.' . $payload . '.' . $signature;

        $this->assertVerificationError(new OidcIdTokenVerifier($this->jwks($key['jwk'])), $unsupported, 'id_token_algorithm_invalid');
    }

    public function testItRejectsMalformedToken(): void
    {
        $this->assertVerificationError(
            new OidcIdTokenVerifier($this->jwks($this->key('current')['jwk'])),
            'not-a-jwt',
            'id_token_malformed',
        );
    }

    public function testItRefreshesJwksForAnUnknownKidAndAcceptsTheRotatedKey(): void
    {
        $oldKey = $this->key('old');
        $currentKey = $this->key('current');
        $jwks = new class ($oldKey['jwk'], $currentKey['jwk']) implements OidcJwksProviderInterface {
            /**
             * @var list<bool>
             */
            public array $refreshes = [];

            /**
             * @var array<string, string>
             */
            private readonly array $oldKey;

            /**
             * @var array<string, string>
             */
            private readonly array $currentKey;

            /**
             * @param array<string, string> $oldKey
             * @param array<string, string> $currentKey
             */
            public function __construct(array $oldKey, array $currentKey)
            {
                $this->oldKey = $oldKey;
                $this->currentKey = $currentKey;
            }

            /** @return array<string, Key> */
            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): array
            {
                $this->refreshes[] = $refresh;

                return JWK::parseKeySet(['keys' => [$refresh ? $this->currentKey : $this->oldKey]], 'RS256');
            }
        };

        $identity = (new OidcIdTokenVerifier($jwks))->verify($this->token($currentKey, $this->claims()), $this->configuration(), $this->metadata(), 'expected-nonce', 1000);

        self::assertSame('subject-1', $identity->subject());
        self::assertSame([false, true], $jwks->refreshes);
    }

    public function testItRejectsUnknownKidAfterOneJwksRefresh(): void
    {
        $signingKey = $this->key('unknown');
        $knownKey = $this->key('known');
        $jwks = new class ($knownKey['jwk']) implements OidcJwksProviderInterface {
            /**
             * @var list<bool>
             */
            public array $refreshes = [];

            /**
             * @var array<string, string>
             */
            private readonly array $key;

            /**
             * @param array<string, string> $key
             */
            public function __construct(array $key)
            {
                $this->key = $key;
            }

            /** @return array<string, Key> */
            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): array
            {
                $this->refreshes[] = $refresh;

                return JWK::parseKeySet(['keys' => [$this->key]], 'RS256');
            }
        };

        $this->assertVerificationError(new OidcIdTokenVerifier($jwks), $this->token($signingKey, $this->claims()), 'id_token_signature_invalid');
        self::assertSame([false, true], $jwks->refreshes);
    }

    public function testItRejectsAnInvalidSignatureAfterOneJwksRefresh(): void
    {
        $signingKey = $this->key('current');
        $otherKey = $this->key('current');
        $jwks = new class ($otherKey['jwk']) implements OidcJwksProviderInterface {
            /**
             * @var list<bool>
             */
            public array $refreshes = [];

            /**
             * @var array<string, string>
             */
            private readonly array $key;

            /**
             * @param array<string, string> $key
             */
            public function __construct(array $key)
            {
                $this->key = $key;
            }

            /** @return array<string, Key> */
            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): array
            {
                $this->refreshes[] = $refresh;

                return JWK::parseKeySet(['keys' => [$this->key]], 'RS256');
            }
        };

        $this->assertVerificationError(new OidcIdTokenVerifier($jwks), $this->token($signingKey, $this->claims()), 'id_token_signature_invalid');
        self::assertSame([false, true], $jwks->refreshes);
    }

    /**
     * @param array{privateKey: string, jwk: array<string, string>} $key
     * @param array<string, mixed> $claims
     */
    private function token(array $key, array $claims): string
    {
        return JWT::encode($claims, $key['privateKey'], 'RS256', $key['jwk']['kid']);
    }

    /** @return array<string, mixed> */
    private function claims(): array
    {
        return ['iss' => 'https://issuer.example.test', 'sub' => 'subject-1', 'aud' => 'client-id', 'azp' => 'client-id', 'exp' => 1060, 'iat' => 1000, 'nonce' => 'expected-nonce', 'email' => 'identity@example.test', 'email_verified' => true, 'name' => 'Identity User', 'groups' => ['/workspace/']];
    }

    private function configuration(): OidcProviderConfiguration
    {
        return new OidcProviderConfiguration('provider-a', true, 'https://issuer.example.test', 'client-id', 'client-secret', 'https://host.example.test/admin/auth/keycloak/callback', ['openid', 'profile', 'email'], null, ['RS256'], 'https://host.example.test/admin/login');
    }

    private function metadata(): OidcProviderMetadata
    {
        return new OidcProviderMetadata('https://issuer.example.test', 'https://issuer.example.test/auth', 'https://issuer.example.test/token', 'https://issuer.example.test/certs', ['S256']);
    }

    /**
     * @param array<string, string> $key
     */
    private function jwks(array $key): OidcJwksProviderInterface
    {
        return new class ($key) implements OidcJwksProviderInterface {
            /** @param array<string, string> $key */
            public function __construct(private readonly array $key) {}

            /** @return array<string, Key> */
            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): array
            {
                return JWK::parseKeySet(['keys' => [$this->key]], 'RS256');
            }
        };
    }

    /**
     * Vytvori RSA klic a jeho verejnou JWKS reprezentaci
     *
     * @return array{privateKey: string, jwk: array<string, string>}
     */
    private function key(string $kid): array
    {
        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $keyPair);
        self::assertTrue(openssl_pkey_export($keyPair, $privateKey));
        $details = openssl_pkey_get_details($keyPair);
        self::assertIsArray($details);
        self::assertIsArray($details['rsa'] ?? null);

        return [
            'privateKey' => $privateKey,
            'jwk' => [
                'kty' => 'RSA',
                'kid' => $kid,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    /**
     * Zakoduje binarni hodnotu pro testovaci JWK fixture
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function assertVerificationError(OidcIdTokenVerifier $verifier, string $token, string $expectedCode): void
    {
        try {
            $verifier->verify($token, $this->configuration(), $this->metadata(), 'expected-nonce', 1000);
            self::fail('Expected ID token verification to fail.');
        } catch (OidcProtocolException $exception) {
            self::assertSame($expectedCode, $exception->getMessage());
        }
    }
}
