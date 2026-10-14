<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use LogicException;
use RuntimeException;

/**
 * Sifruje TOTP secrety hostem dodanym AES-GCM klicem
 */
final readonly class MfaSecretCipher
{
    private const string CIPHER = 'aes-256-gcm';

    /**
     * Ulozi binary klic nebo explicitni disabled stav cipheru
     */
    private function __construct(private ?string $key) {}

    /**
     * Vytvori cipher, ktery odmita kazdou kryptografickou operaci
     */
    public static function disabled(): self
    {
        return new self(null);
    }

    /**
     * Vytvori cipher z hostem dodaneho 256bitoveho binary klice
     */
    public static function fromKey(string $key): self
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('MFA encryption key must contain exactly 32 bytes.');
        }

        return new self($key);
    }

    /**
     * Sifruje hodnotu vazanou na konkretniho uzivatele
     */
    public function encrypt(string $value, int $userId): string
    {
        $key = $this->encryptionKey();
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $this->aad($userId), 16);
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt MFA secret.');
        }

        return 'v1.' . base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Desifruje hodnotu pouze pro puvodniho uzivatele
     */
    public function decrypt(string $envelope, int $userId): string
    {
        $key = $this->encryptionKey();
        if (!str_starts_with($envelope, 'v1.')) {
            throw new RuntimeException('Invalid MFA secret envelope.');
        }
        $payload = base64_decode(substr($envelope, 3), true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Invalid MFA secret envelope.');
        }
        $nonce = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $value = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $this->aad($userId));
        if ($value === false) {
            throw new RuntimeException('Unable to decrypt MFA secret.');
        }

        return $value;
    }

    /**
     * Vytvari dodatecne overena data vazana na uzivatele
     */
    private function aad(int $userId): string
    {
        return 'system_user_totp:' . $userId;
    }

    /**
     * Vrati aktivni sifrovaci klic nebo odmitne disabled MFA flow
     */
    private function encryptionKey(): string
    {
        if ($this->key === null) {
            throw new LogicException('MFA secret cipher is disabled.');
        }

        return $this->key;
    }
}
