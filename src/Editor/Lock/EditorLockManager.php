<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Framework\Database\Database;
use Throwable;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final class EditorLockManager
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private readonly Database $database,
        private readonly CurrentUserProvider $currentUser,
        private readonly EditorLockOwnerReleaserInterface $ownerReleaser,
    ) {}

    /**
     * Ziskava zamek zaznamu pro aktualniho uzivatele
     */
    public function acquire(string $resourceType, string $resourceId): EditorLockAcquisition
    {
        $identity = $this->currentIdentity();
        if ($identity === null) {
            return new EditorLockAcquisition(false);
        }

        return $this->database->transaction(function () use ($resourceType, $resourceId, $identity): EditorLockAcquisition {
            $rows = $this->lockRows($resourceType, $resourceId, true);
            if ($rows !== [] && (string) $rows[0]['owner_key'] !== $identity->key()) {
                return new EditorLockAcquisition(false, $this->owner($rows[0]));
            }
            if ($rows === []) {
                try {
                    $this->database->statement('INSERT INTO admin_editor_lock (resource_type, resource_id, owner_key, owner_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$resourceType, $resourceId, $identity->key(), $identity->localUserId(), $this->now(), $this->now()]);
                } catch (Throwable) {
                    $retry = $this->lockRows($resourceType, $resourceId);
                    if ($retry === [] || (string) $retry[0]['owner_key'] !== $identity->key()) {
                        return new EditorLockAcquisition(false, $retry === [] ? null : $this->owner($retry[0]));
                    }
                }
            }
            $this->touch($resourceType, $resourceId, $identity->key());

            return new EditorLockAcquisition(true);
        });
    }

    /**
     * Overuje, ze aktualni uzivatel vlastni zamek zaznamu
     */
    public function assertOwned(string $resourceType, string $resourceId): void
    {
        if (!$this->refresh($resourceType, $resourceId)) {
            throw new EditorLockException('Editor lock is not owned by the current user.');
        }
    }

    /**
     * Rozhoduje stav allowsaction
     */
    public function allowsAction(string $resourceType, string $resourceId): bool
    {
        $identity = $this->currentIdentity();
        $ownerKey = $this->ownerKey($resourceType, $resourceId);

        return $ownerKey === null || ($identity !== null && $ownerKey === $identity->key());
    }

    /**
     * Zpracovava hodnotu conflict v konfiguraci editoru
     */
    public function conflict(string $resourceType, string $resourceId): ?EditorLockOwner
    {
        $rows = $this->lockRows($resourceType, $resourceId);
        if ($rows === []) {
            return null;
        }
        $identity = $this->currentIdentity();

        return $identity !== null && (string) $rows[0]['owner_key'] === $identity->key() ? null : $this->owner($rows[0]);
    }

    /**
     * Prodluzuje platnost zamku zaznamu aktualniho uzivatele
     */
    private function refresh(string $resourceType, string $resourceId): bool
    {
        $identity = $this->currentIdentity();

        if ($identity === null) {
            return false;
        }

        $this->touch($resourceType, $resourceId, $identity->key());

        return $this->ownerKey($resourceType, $resourceId) === $identity->key();
    }

    /**
     * Uvolnuje zamek zaznamu vlastneny aktualnim uzivatelem
     */
    public function release(string $resourceType, string $resourceId): bool
    {
        $identity = $this->currentIdentity();

        return $identity !== null && $this->database->statement('DELETE FROM admin_editor_lock WHERE resource_type = ? AND resource_id = ? AND owner_key = ?', [$resourceType, $resourceId, $identity->key()]) === 1;
    }

    /**
     * Zpracovava hodnotu releaseallforcurrentuser v konfiguraci editoru
     */
    public function releaseAllForCurrentUser(): void
    {
        $identity = $this->currentIdentity();
        if ($identity !== null) {
            $this->database->statement('DELETE FROM admin_editor_lock WHERE owner_key = ?', [$identity->key()]);
        }
    }

    /**
     * Zpracovava hodnotu releaseownedbyuser v konfiguraci editoru
     */
    public function releaseOwnedByUser(int $userId): void
    {
        $this->ownerReleaser->releaseOwnedByUser($userId);
    }

    /**
     * Zpracovava hodnotu touch v konfiguraci editoru
     */
    private function touch(string $resourceType, string $resourceId, string $ownerKey): int
    {
        return $this->database->statement('UPDATE admin_editor_lock SET updated_at = ? WHERE resource_type = ? AND resource_id = ? AND owner_key = ?', [$this->now(), $resourceType, $resourceId, $ownerKey]);
    }

    /**
     * Zpracovava hodnotu ownerkey v konfiguraci editoru
     */
    private function ownerKey(string $resourceType, string $resourceId): ?string
    {
        $rows = $this->database->select(
            'SELECT owner_key FROM admin_editor_lock WHERE resource_type = ? AND resource_id = ? LIMIT 1',
            [$resourceType, $resourceId],
        );

        return $rows === [] ? null : (string) $rows[0]['owner_key'];
    }

    /**
     * Zpracovava hodnotu lockrows v konfiguraci editoru
     * @return list<array<string,mixed>>
     */
    private function lockRows(string $resourceType, string $resourceId, bool $forUpdate = false): array
    {
        return $this->database->select('SELECT l.owner_key, l.owner_user_id, u.first_name, u.last_name, u.email FROM admin_editor_lock l LEFT JOIN system_user u ON u.id = l.owner_user_id WHERE l.resource_type = ? AND l.resource_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [$resourceType, $resourceId]);
    }

    /**
     * Zpracovava hodnotu currentidentity v konfiguraci editoru
     */
    private function currentIdentity(): ?EditorLockIdentity
    {
        $user = $this->currentUser->currentUser();

        return $user === null ? null : EditorLockIdentity::local($user);
    }

    /**
     * Zpracovava hodnotu owner v konfiguraci editoru
     * @param array<string, mixed> $row
     */
    private function owner(array $row): EditorLockOwner
    {
        if ($row['owner_user_id'] === null) {
            return new EditorLockOwner(null, 'Neznámý uživatel');
        }
        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));

        return new EditorLockOwner((int) $row['owner_user_id'], $name !== '' ? $name : (string) $row['email']);
    }

    /**
     * Zpracovava hodnotu now v konfiguraci editoru
     */
    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
