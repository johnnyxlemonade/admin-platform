<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Editor;

use Lemonade\Admin\Auth\LocalAuthenticationProvider;

/**
 * Nese normalizovany vstup Users editoru po schema validaci
 */
final readonly class UserEditorInput
{
    /**
     * Nastavuje hodnoty profilu, lifecycle, credentialu, role a overrides
     */
    public function __construct(
        private string $firstName,
        private string $lastName,
        private string $email,
        private ?string $phone,
        private bool $active,
        private int $version,
        private ?string $localPassword,
        private ?int $roleId,
        /**
         * @var array<string, bool>|null
         */
        private ?array $permissionStates,
    ) {}

    /**
     * Vytvari normalizovany vstup z dat overenych editorem
     *
     * @param array<string, mixed> $data
     */
    public static function fromValidated(array $data): self
    {
        $roleId = isset($data['role']) && $data['role'] !== '' ? (int) $data['role'] : null;

        $overrides = null;
        if (array_key_exists('permissions', $data) && is_array($data['permissions'])) {
            $overrides = [];
            foreach ($data['permissions'] as $code => $allowed) {
                if (is_string($code) && in_array((string) $allowed, ['0', '1'], true)) {
                    $overrides[$code] = (string) $allowed === '1';
                }
            }
        }

        return new self(
            trim((string) $data['first_name']),
            trim((string) $data['last_name']),
            LocalAuthenticationProvider::normalizeEmail((string) $data['email']),
            isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
            (string) $data['active'] === '1',
            (int) ($data['version'] ?? 1),
            isset($data['local_password']) && (string) $data['local_password'] !== '' ? (string) $data['local_password'] : null,
            $roleId,
            $overrides,
        );
    }

    public function email(): string
    {
        return $this->email;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function localPassword(): ?string
    {
        return $this->localPassword;
    }

    public function roleId(): ?int
    {
        return $this->roleId;
    }

    /**
     * Rozlisuje neuvedene overrides od pozadovane zmeny effective permission stavu
     *
     * @return array<string, bool>|null
     */
    public function permissionStates(): ?array
    {
        return $this->permissionStates;
    }
}
