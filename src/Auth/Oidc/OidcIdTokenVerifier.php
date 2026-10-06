<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use JsonException;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;

/**
 * Zpracovava overenou identitu uzivatele
 */
final class OidcIdTokenVerifier
{
    private const CLOCK_SKEW_SECONDS = 60;
    private const MAX_ISSUED_AT_AGE_SECONDS = 86400;

    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(private readonly OidcJwksProviderInterface $jwks) {}

    /**
     * Overuje podpis a platnost OIDC tokenu
     */
    public function verify(
        string $idToken,
        OidcProviderConfiguration $configuration,
        OidcProviderMetadata $metadata,
        string $expectedNonce,
        ?int $now = null,
    ): VerifiedExternalIdentity {
        $now ??= time();
        $header = $this->header($idToken);
        $algorithm = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;
        if (!is_string($algorithm) || !in_array($algorithm, $configuration->allowedIdTokenAlgorithms(), true) || $algorithm !== 'RS256') {
            throw new OidcProtocolException('id_token_algorithm_invalid');
        }
        if (!is_string($kid) || $kid === '') {
            throw new OidcProtocolException('id_token_kid_missing');
        }

        $jws = $this->verifiedJws($idToken, $metadata, $kid, false);
        if ($jws === null) {
            $jws = $this->verifiedJws($idToken, $metadata, $kid, true);
        }
        if ($jws === null) {
            throw new OidcProtocolException('id_token_signature_invalid');
        }
        $payload = $jws->getPayload();
        if (!is_string($payload)) {
            throw new OidcProtocolException('id_token_payload_invalid');
        }
        try {
            $claims = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OidcProtocolException('id_token_payload_invalid');
        }
        if (!is_array($claims)) {
            throw new OidcProtocolException('id_token_payload_invalid');
        }

        $this->validateClaims($claims, $configuration, $metadata, $expectedNonce, $now);

        return new VerifiedExternalIdentity(
            $configuration->provider(),
            (string) $claims['iss'],
            (string) $claims['sub'],
            is_string($claims['email'] ?? null) ? $claims['email'] : null,
            $this->groups($claims['groups'] ?? null),
            is_string($claims['given_name'] ?? null) ? $claims['given_name'] : null,
            is_string($claims['family_name'] ?? null) ? $claims['family_name'] : null,
        );
    }

    /**
     * Vraci nebo zpracovava hodnotu header pro overeni identity
     * @return array<string, mixed>
     */
    private function header(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new OidcProtocolException('id_token_malformed');
        }
        $encoded = strtr($parts[0], '-_', '+/');
        $decoded = base64_decode($encoded . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if (!is_string($decoded)) {
            throw new OidcProtocolException('id_token_malformed');
        }
        try {
            $header = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OidcProtocolException('id_token_malformed');
        }

        if (!is_array($header)) {
            throw new OidcProtocolException('id_token_malformed');
        }

        return $header;
    }

    /**
     * Vraci nebo zpracovava hodnotu verifiedjws pro overeni identity
     */
    private function verifiedJws(string $idToken, OidcProviderMetadata $metadata, string $kid, bool $refresh): ?\Jose\Component\Signature\JWS
    {
        try {
            $signature = null;
            $loader = new JWSLoader(
                new JWSSerializerManager([new CompactSerializer()]),
                new JWSVerifier(new AlgorithmManager([new RS256()])),
                null,
            );

            $keySet = $this->jwks->keySet($metadata, $refresh);
            if (!$keySet->has($kid)) {
                return null;
            }

            return $loader->loadAndVerifyWithKey($idToken, $keySet->get($kid), $signature);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Vraci nebo zpracovava hodnotu validateclaims pro overeni identity
     * @param array<string, mixed> $claims
     */
    private function validateClaims(array $claims, OidcProviderConfiguration $configuration, OidcProviderMetadata $metadata, string $expectedNonce, int $now): void
    {
        if (($claims['iss'] ?? null) !== $configuration->issuer() || $metadata->issuer() !== $configuration->issuer()) {
            throw new OidcProtocolException('id_token_issuer_invalid');
        }
        $audiences = is_string($claims['aud'] ?? null) ? [$claims['aud']] : (is_array($claims['aud'] ?? null) ? $claims['aud'] : []);
        if (!in_array($configuration->clientId(), $audiences, true)) {
            throw new OidcProtocolException('id_token_audience_invalid');
        }
        $authorizedParty = $claims['azp'] ?? null;
        if ((count($audiences) > 1 && $authorizedParty !== $configuration->clientId()) || ($authorizedParty !== null && $authorizedParty !== $configuration->clientId())) {
            throw new OidcProtocolException('id_token_authorized_party_invalid');
        }
        if (!is_int($claims['exp'] ?? null) || $claims['exp'] < $now - self::CLOCK_SKEW_SECONDS) {
            throw new OidcProtocolException('id_token_expired');
        }
        if (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > $now + self::CLOCK_SKEW_SECONDS)) {
            throw new OidcProtocolException('id_token_not_active');
        }
        if (!is_int($claims['iat'] ?? null) || $claims['iat'] > $now + self::CLOCK_SKEW_SECONDS || $claims['iat'] < $now - self::MAX_ISSUED_AT_AGE_SECONDS) {
            throw new OidcProtocolException('id_token_issued_at_invalid');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($expectedNonce, $claims['nonce'])) {
            throw new OidcProtocolException('id_token_nonce_invalid');
        }
        if (!is_string($claims['sub'] ?? null) || trim($claims['sub']) === '') {
            throw new OidcProtocolException('id_token_subject_invalid');
        }
    }

    /**
     * Vraci nebo zpracovava hodnotu groups pro overeni identity
     * @return list<string>
     */
    private function groups(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $groups = [];
        foreach ($value as $group) {
            if (is_string($group) && trim($group) !== '') {
                $groups[] = trim($group, '/');
            }
        }

        return array_values(array_unique($groups));
    }
}
