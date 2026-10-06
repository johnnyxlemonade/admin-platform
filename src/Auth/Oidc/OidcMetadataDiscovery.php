<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use JsonException;
use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientInterface;

/**
 * Zpracovava overenou identitu uzivatele
 */
final class OidcMetadataDiscovery
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(private readonly ClientInterface $http) {}

    /**
     * Nacita metadata OIDC poskytovatele
     */
    public function discover(OidcProviderConfiguration $configuration): OidcProviderMetadata
    {
        try {
            $response = $this->http->sendRequest(new Request('GET', rtrim($configuration->issuer(), '/') . '/.well-known/openid-configuration', ['Accept' => 'application/json']));
            if ($response->getStatusCode() !== 200) {
                throw new OidcProtocolException('discovery_unavailable');
            }
            $metadata = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OidcProtocolException('discovery_invalid');
        } catch (OidcProtocolException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new OidcProtocolException('discovery_unavailable');
        }
        if (!is_array($metadata) || ($metadata['issuer'] ?? null) !== $configuration->issuer()) {
            throw new OidcProtocolException('discovery_issuer_invalid');
        }

        return new OidcProviderMetadata(
            $configuration->issuer(),
            $this->string($metadata, 'authorization_endpoint'),
            $this->string($metadata, 'token_endpoint'),
            $this->string($metadata, 'jwks_uri'),
            $this->strings($metadata['code_challenge_methods_supported'] ?? []),
            $this->nullableString($metadata, 'end_session_endpoint'),
        );
    }

    /**
     * Vraci nebo zpracovava hodnotu string pro overeni identity
     * @param array<string, mixed> $metadata
     */
    private function string(array $metadata, string $key): string
    {
        $value = $metadata[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new OidcProtocolException('discovery_invalid');
        }

        return $value;
    }

    /**
     * Vraci nebo zpracovava hodnotu nullablestring pro overeni identity
     * @param array<string, mixed> $metadata
     */
    private function nullableString(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Vraci nebo zpracovava hodnotu strings pro overeni identity
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn(mixed $item): bool => is_string($item)));
    }
}
