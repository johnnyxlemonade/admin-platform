<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Oidc\HttpOidcJwksProvider;
use Lemonade\Admin\Auth\Oidc\OidcProtocolException;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpOidcJwksProviderTest extends TestCase
{
    public function testItRejectsMalformedJwks(): void
    {
        $provider = new HttpOidcJwksProvider(new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], '{not-json');
            }
        });

        try {
            $provider->keySet($this->metadata());
            self::fail('Expected malformed JWKS to be rejected.');
        } catch (OidcProtocolException $exception) {
            self::assertSame('jwks_invalid', $exception->getMessage());
        }
    }

    private function metadata(): OidcProviderMetadata
    {
        return new OidcProviderMetadata(
            'https://issuer.example.test',
            'https://issuer.example.test/auth',
            'https://issuer.example.test/token',
            'https://issuer.example.test/certs',
            ['S256'],
        );
    }
}
