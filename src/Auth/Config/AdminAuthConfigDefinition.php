<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Config;

use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\RateLimit\RateLimitPolicy;

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
            defaultRole: $this->nullableString($oidc, 'default_role'),
        );
    }

    /**
     * Sestavi hostem deklarovanou local TOTP policy administrace
     */
    public function mfaConfiguration(): AdminMfaConfiguration
    {
        $mfa = $this->toArray()['mfa'] ?? [];
        if (!is_array($mfa)) {
            throw new \InvalidArgumentException('Admin auth MFA configuration must be a mapping.');
        }

        return new AdminMfaConfiguration(
            localRequirement: $this->string($mfa, 'local_requirement', 'disabled'),
            issuer: $this->nullableString($mfa, 'issuer'),
            encryptionKey: $this->nullableString($mfa, 'encryption_key'),
        );
    }

    /**
     * Sestavi hostem deklarovane zapnuti a fixed-window policy pro local login a MFA
     */
    public function throttlingConfiguration(): AdminAuthenticationThrottleConfiguration
    {
        $throttling = $this->toArray()['throttling'] ?? [];
        if (!is_array($throttling)) {
            throw new \InvalidArgumentException('Admin auth throttling configuration must be a mapping.');
        }

        $enabled = $this->boolean($throttling, 'enabled', true);
        if (!$enabled) {
            return new AdminAuthenticationThrottleConfiguration(enabled: false);
        }

        return new AdminAuthenticationThrottleConfiguration(
            enabled: true,
            loginIp: $this->rateLimitPolicy($throttling, 'login', 'ip'),
            loginIdentity: $this->rateLimitPolicy($throttling, 'login', 'identity'),
            mfaIp: $this->rateLimitPolicy($throttling, 'mfa', 'ip'),
            mfaIdentity: $this->rateLimitPolicy($throttling, 'mfa', 'identity'),
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
     * Vrati boolean hodnotu Admin Auth konfigurace s volitelnym vychozim stavem
     *
     * @param array<string,mixed> $values
     */
    private function boolean(array $values, string $key, bool $default): bool
    {
        $value = $values[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException(sprintf('Admin auth configuration value "%s" must be a boolean.', $key));
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

    /**
     * Overi jednu povinnou fixed-window policy z host konfigurace
     *
     * @param array<string,mixed> $throttling
     */
    private function rateLimitPolicy(array $throttling, string $flow, string $bucket): RateLimitPolicy
    {
        $flowConfiguration = $throttling[$flow] ?? null;
        if (!is_array($flowConfiguration)) {
            throw new \InvalidArgumentException(sprintf('Admin auth throttling requires mapping "%s".', $flow));
        }

        $bucketConfiguration = $flowConfiguration[$bucket] ?? null;
        if (!is_array($bucketConfiguration)) {
            throw new \InvalidArgumentException(sprintf('Admin auth throttling requires mapping "%s.%s".', $flow, $bucket));
        }

        return new RateLimitPolicy(
            attempts: $this->positiveInteger($bucketConfiguration, 'attempts', $flow, $bucket),
            windowSeconds: $this->positiveInteger($bucketConfiguration, 'window_seconds', $flow, $bucket),
        );
    }

    /**
     * Vraci povinne kladne cele cislo z jedne limiter policy
     *
     * @param array<string,mixed> $configuration
     */
    private function positiveInteger(array $configuration, string $key, string $flow, string $bucket): int
    {
        $value = $configuration[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException(sprintf('Admin auth throttling value "%s.%s.%s" must be a positive integer.', $flow, $bucket, $key));
        }

        return $value;
    }
}
