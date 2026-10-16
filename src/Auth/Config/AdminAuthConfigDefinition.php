<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Config;

use InvalidArgumentException;
use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfig;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;

/**
 * Prevadi deklarovanou host konfiguraci na runtime autentizace administrace
 *
 * Host ji mapuje ve svem ConfigMap na YAML modul admin_auth. Nactenou definici
 * vyuzije AdminAuthRuntimeServiceProvider; bezpecnostni session policy zustava
 * canonical soucasti Admin Platform a host ji muze pouze zprisnit.
 */
final class AdminAuthConfigDefinition extends AbstractConfigDefinition
{
    public const int MAXIMUM_SESSION_LIFETIME_SECONDS = 172800;

    private const string SESSION_COOKIE_NAME = 'LEMONADE_ADMIN_SESSION';
    private const string SESSION_STORAGE_PATH = 'sessions/admin';

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
     * Odvozuje izolovanou Admin session z backendu zvoleneho host aplikaci
     */
    public function sessionConfiguration(SessionConfig $hostConfiguration): SessionConfig
    {
        $session = $this->sessionValues();

        return SessionConfigDefinition::create()
            ->cookieName($this->sessionString($session, 'cookie_name', self::SESSION_COOKIE_NAME))
            ->lifetime($this->sessionLifetime($session))
            ->nativePath($this->sessionString($session, 'storage_path', self::SESSION_STORAGE_PATH))
            ->filePath($this->sessionString($session, 'storage_path', self::SESSION_STORAGE_PATH))
            ->redisPrefix($this->sessionString(
                $session,
                'redis_prefix',
                $hostConfiguration->redis()->prefix() . 'admin:',
            ))
            ->applyTo($hostConfiguration);
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
     * Sestavi canonical Admin policy s volitelnym hostem deklarovanym vypnutim
     */
    public function throttlingConfiguration(): AdminAuthenticationThrottleConfiguration
    {
        $throttling = $this->toArray()['throttling'] ?? [];
        if (!is_array($throttling)) {
            throw new \InvalidArgumentException('Admin auth throttling configuration must be a mapping.');
        }

        $enabled = $this->boolean($throttling, 'enabled', true);
        if (!$enabled) {
            return AdminAuthenticationThrottleConfiguration::disabled();
        }

        return AdminAuthenticationThrottleConfiguration::defaults(
            loginIpEnabled: $this->bucketEnabled($throttling, 'login', 'ip'),
            loginIdentityEnabled: $this->bucketEnabled($throttling, 'login', 'identity'),
            mfaIpEnabled: $this->bucketEnabled($throttling, 'mfa', 'ip'),
            mfaIdentityEnabled: $this->bucketEnabled($throttling, 'mfa', 'identity'),
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
     * Vrati mapovani transportni konfigurace Admin session
     *
     * @return array<string,mixed>
     */
    private function sessionValues(): array
    {
        $session = $this->toArray()['session'] ?? [];
        if (!is_array($session)) {
            throw new InvalidArgumentException('Admin auth session configuration must be a mapping.');
        }

        /** @var array<string,mixed> $session */
        return $session;
    }

    /**
     * Vrati kladny Admin lifetime nepresahujici canonical absolutni maximum
     *
     * @param array<string,mixed> $session
     */
    private function sessionLifetime(array $session): int
    {
        $lifetime = $session['lifetime'] ?? self::MAXIMUM_SESSION_LIFETIME_SECONDS;
        if (!is_int($lifetime) || $lifetime < 1 || $lifetime > self::MAXIMUM_SESSION_LIFETIME_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Admin session lifetime must be between 1 and %d seconds.',
                self::MAXIMUM_SESSION_LIFETIME_SECONDS,
            ));
        }

        return $lifetime;
    }

    /**
     * Vrati neprazdnou textovou hodnotu transportni konfigurace Admin session
     *
     * @param array<string,mixed> $session
     */
    private function sessionString(array $session, string $key, string $default): string
    {
        $value = $session[$key] ?? $default;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Admin session configuration value "%s" must be a non-empty string.',
                $key,
            ));
        }

        return trim($value);
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
     * Vrati zapnuti jednoho bucketu s vychozi hodnotou true
     *
     * @param array<string,mixed> $throttling
     */
    private function bucketEnabled(array $throttling, string $flow, string $bucket): bool
    {
        $flowValues = $throttling[$flow] ?? [];
        if (!is_array($flowValues)) {
            throw new \InvalidArgumentException('Admin auth throttling flow must be a mapping.');
        }
        $bucketValues = $flowValues[$bucket] ?? [];
        if (!is_array($bucketValues)) {
            throw new \InvalidArgumentException('Admin auth throttling bucket must be a mapping.');
        }

        return $this->boolean($bucketValues, 'enabled', true);
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
