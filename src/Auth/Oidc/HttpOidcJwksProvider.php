<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use JsonException;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientInterface;

/**
 * Poskytuje data potrebna pro overeni identity
 */
final class HttpOidcJwksProvider implements OidcJwksProviderInterface
{
    /** @var array<string, array<string, Key>> */
    private array $cache = [];

    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(private readonly ClientInterface $http) {}

    /**
     * Nacita a vraci RS256 klice JWKS indexovane podle kid
     *
     * @return array<string, Key>
     */
    public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): array
    {
        $uri = $metadata->jwksUri();
        if (!$refresh && isset($this->cache[$uri])) {
            return $this->cache[$uri];
        }

        try {
            $response = $this->http->sendRequest(new Request('GET', $uri, ['Accept' => 'application/json']));
            if ($response->getStatusCode() !== 200) {
                throw new OidcProtocolException('jwks_unavailable');
            }
            $payload = $response->getBody()->getContents();
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new OidcProtocolException('jwks_invalid');
            }
            $keySet = JWK::parseKeySet($data, 'RS256');
        } catch (JsonException) {
            throw new OidcProtocolException('jwks_invalid');
        } catch (OidcProtocolException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new OidcProtocolException('jwks_unavailable');
        }

        $this->cache[$uri] = $keySet;

        return $keySet;
    }
}
