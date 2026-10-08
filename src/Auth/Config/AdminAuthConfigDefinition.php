<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Config;

use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Prevadi deklarovanou host konfiguraci na OIDC runtime administrace
 *
 * Host ji mapuje ve svem ConfigMap na YAML modul admin_auth. Nactenou definici
 * vyuzije AdminAuthRuntimeServiceProvider; callback a logout URL odvozuje z
 * AdminRoutingConfiguration, pokud je host explicitne nenastavi.
 */
final class AdminAuthConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Vytvori vypnutou vychozi konfiguraci OIDC pro lokalni prihlaseni
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Vrati stabilni klic konfigurace autentizace administrace
     */
    public static function moduleKey(): string
    {
        return 'admin_auth';
    }

    /**
     * Sestavi overenou konfiguraci jednoho OIDC poskytovatele administrace
     */
    public function oidcConfiguration(AdminRoutingConfiguration $routing): OidcProviderConfiguration
    {
        $oidc = $this->oidcValues();
        $enabled = $this->boolean($oidc, 'enabled', false);
        $baseUrl = rtrim($this->string($oidc, 'base_url'), '/');
        $redirectUri = $this->string($oidc, 'redirect_uri');
        $postLogoutRedirectUri = $this->string($oidc, 'post_logout_redirect_uri');

        if ($enabled && $baseUrl === '' && ($redirectUri === '' || $postLogoutRedirectUri === '')) {
            throw new \InvalidArgumentException('Enabled OIDC requires base_url when callback or post-logout URL is not explicit.');
        }

        return new OidcProviderConfiguration(
            provider: $this->string($oidc, 'provider', 'oidc'),
            enabled: $enabled,
            issuer: $this->string($oidc, 'issuer'),
            clientId: $this->string($oidc, 'client_id'),
            clientSecret: $this->string($oidc, 'client_secret'),
            redirectUri: $redirectUri === '' ? $baseUrl . $routing->path('/auth/keycloak/callback') : $redirectUri,
            scopes: $this->stringList($oidc, 'scopes'),
            requiredGroup: $this->nullableString($oidc, 'required_group'),
            allowedIdTokenAlgorithms: $this->stringList($oidc, 'allowed_id_token_algorithms'),
            postLogoutRedirectUri: $postLogoutRedirectUri === '' ? $baseUrl . $routing->path('/login') : $postLogoutRedirectUri,
        );
    }

    /**
     * Vrati mapovani OIDC hodnot nebo ohlasi neplatnou konfiguraci
     *
     * @return array<string,mixed>
     */
    private function oidcValues(): array
    {
        $oidc = $this->toArray()['oidc'] ?? [];
        if (!is_array($oidc)) {
            throw new \InvalidArgumentException('Admin auth OIDC configuration must be a mapping.');
        }

        /** @var array<string,mixed> $oidc */
        return $oidc;
    }

    /**
     * Vrati textovou hodnotu konfigurace s volitelnym vychozim textem
     *
     * @param array<string,mixed> $values
     */
    private function string(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Admin auth OIDC value "%s" must be a string.', $key));
        }

        return trim($value);
    }

    /**
     * Vrati boolean hodnotu konfigurace s volitelnym vychozim stavem
     *
     * @param array<string,mixed> $values
     */
    private function boolean(array $values, string $key, bool $default): bool
    {
        $value = $values[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException(sprintf('Admin auth OIDC value "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /**
     * Vrati prazdny text jako null pro volitelnou skupinu OIDC
     *
     * @param array<string,mixed> $values
     */
    private function nullableString(array $values, string $key): ?string
    {
        $value = $this->string($values, $key);

        return $value === '' ? null : $value;
    }

    /**
     * Vrati seznam textovych hodnot konfigurace OIDC z YAML listu nebo environmentu
     *
     * @param array<string,mixed> $values
     * @return list<string>
     */
    private function stringList(array $values, string $key): array
    {
        $value = $values[$key] ?? [];
        if (is_string($value)) {
            $splitValues = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
            $value = $splitValues === false ? [] : $splitValues;
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException(sprintf('Admin auth OIDC value "%s" must be a list or whitespace-separated string.', $key));
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new \InvalidArgumentException(sprintf('Admin auth OIDC list "%s" must contain non-empty strings.', $key));
            }
            $items[] = trim($item);
        }

        return array_values(array_unique($items));
    }
}
