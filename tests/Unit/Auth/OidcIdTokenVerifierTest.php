<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
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
        $key = JWKFactory::createRSAKey(2048, ['kid' => 'current', 'use' => 'sig', 'alg' => 'RS256']);
        $verifier = new OidcIdTokenVerifier($this->jwks($key->toPublic()));
        $identity = $verifier->verify($this->token($key, $this->claims()), $this->configuration(), $this->metadata(), 'expected-nonce', 1000);

        self::assertSame('subject-1', $identity->subject());
        self::assertSame('identity@example.test', $identity->email());
        self::assertSame(['workspace'], $identity->groups());
    }

    public function testItRejectsInvalidClaims(): void
    {
        $key = JWKFactory::createRSAKey(2048, ['kid' => 'current', 'use' => 'sig', 'alg' => 'RS256']);
        $verifier = new OidcIdTokenVerifier($this->jwks($key->toPublic()));

        foreach ([
            ['iss' => 'https://unexpected.example.test', 'expected' => 'id_token_issuer_invalid'],
            ['aud' => 'other-client', 'expected' => 'id_token_audience_invalid'],
            ['exp' => 900, 'expected' => 'id_token_expired'],
            ['nbf' => 1061, 'expected' => 'id_token_not_active'],
            ['iat' => 1061, 'expected' => 'id_token_issued_at_invalid'],
            ['nonce' => 'other-nonce', 'expected' => 'id_token_nonce_invalid'],
        ] as $case) {
            $claims = array_replace($this->claims(), $case);
            $this->assertVerificationError($verifier, $this->token($key, $claims), $case['expected']);
        }
    }

    public function testItRejectsUnsupportedAlgorithmBeforeSignatureVerification(): void
    {
        $key = JWKFactory::createRSAKey(2048, ['kid' => 'current', 'use' => 'sig', 'alg' => 'RS256']);
        $token = $this->token($key, $this->claims());
        [, $payload, $signature] = explode('.', $token);
        $unsupported = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'kid' => 'current'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=') . '.' . $payload . '.' . $signature;

        $this->assertVerificationError(new OidcIdTokenVerifier($this->jwks($key->toPublic())), $unsupported, 'id_token_algorithm_invalid');
    }

    public function testItRefreshesJwksForAnUnknownKidAndAcceptsTheRotatedKey(): void
    {
        $oldKey = JWKFactory::createRSAKey(2048, ['kid' => 'old', 'use' => 'sig', 'alg' => 'RS256']);
        $currentKey = JWKFactory::createRSAKey(2048, ['kid' => 'current', 'use' => 'sig', 'alg' => 'RS256']);
        $jwks = new class ($oldKey->toPublic(), $currentKey->toPublic()) implements OidcJwksProviderInterface {
            /** @var list<bool> */
            public array $refreshes = [];

            public function __construct(private readonly JWK $oldKey, private readonly JWK $currentKey) {}

            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): JWKSet
            {
                $this->refreshes[] = $refresh;

                return new JWKSet([$refresh ? $this->currentKey : $this->oldKey]);
            }
        };

        $identity = (new OidcIdTokenVerifier($jwks))->verify($this->token($currentKey, $this->claims()), $this->configuration(), $this->metadata(), 'expected-nonce', 1000);

        self::assertSame('subject-1', $identity->subject());
        self::assertSame([false, true], $jwks->refreshes);
    }

    public function testItRejectsUnknownKidAfterOneJwksRefresh(): void
    {
        $signingKey = JWKFactory::createRSAKey(2048, ['kid' => 'unknown', 'use' => 'sig', 'alg' => 'RS256']);
        $knownKey = JWKFactory::createRSAKey(2048, ['kid' => 'known', 'use' => 'sig', 'alg' => 'RS256']);
        $jwks = new class ($knownKey->toPublic()) implements OidcJwksProviderInterface {
            /** @var list<bool> */
            public array $refreshes = [];

            public function __construct(private readonly JWK $key) {}

            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): JWKSet
            {
                $this->refreshes[] = $refresh;

                return new JWKSet([$this->key]);
            }
        };

        $this->assertVerificationError(new OidcIdTokenVerifier($jwks), $this->token($signingKey, $this->claims()), 'id_token_signature_invalid');
        self::assertSame([false, true], $jwks->refreshes);
    }

    public function testItRejectsAnInvalidSignatureAfterOneJwksRefresh(): void
    {
        $signingKey = JWKFactory::createRSAKey(2048, ['kid' => 'current', 'use' => 'sig', 'alg' => 'RS256']);
        $otherKey = JWKFactory::createRSAKey(2048, ['kid' => 'current', 'use' => 'sig', 'alg' => 'RS256']);
        $jwks = new class ($otherKey->toPublic()) implements OidcJwksProviderInterface {
            /** @var list<bool> */
            public array $refreshes = [];

            public function __construct(private readonly JWK $key) {}

            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): JWKSet
            {
                $this->refreshes[] = $refresh;

                return new JWKSet([$this->key]);
            }
        };

        $this->assertVerificationError(new OidcIdTokenVerifier($jwks), $this->token($signingKey, $this->claims()), 'id_token_signature_invalid');
        self::assertSame([false, true], $jwks->refreshes);
    }

    /** @param array<string, mixed> $claims */
    private function token(JWK $key, array $claims): string
    {
        return (new CompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([new RS256()])))
            ->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature($key, ['alg' => 'RS256', 'kid' => 'current'])
            ->build());
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

    private function jwks(JWK $key): OidcJwksProviderInterface
    {
        return new class ($key) implements OidcJwksProviderInterface {
            public function __construct(private readonly JWK $key) {}

            public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): JWKSet
            {
                return new JWKSet([$this->key]);
            }
        };
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
