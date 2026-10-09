<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use RuntimeException;

/**
 * Sifruje TOTP secrety hostem dodanym AES-GCM klicem
 */
final class MfaSecretCipher
{
    private const CIPHER = 'aes-256-gcm';

    /**
     * Overi a ulozi base64 kodovany 256bitovy sifrovaci klic
     */
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('MFA encryption key must contain exactly 32 bytes.');
        }
    }

    /**
     * Sifruje hodnotu vazanou na konkretniho uzivatele
     */
    public function encrypt(string $value, int $userId): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $this->aad($userId), 16);
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
        $value = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $this->aad($userId));
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
}
