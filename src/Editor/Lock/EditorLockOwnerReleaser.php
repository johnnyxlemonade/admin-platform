<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use Lemonade\Framework\Database\Database;

/**
 * Uvolnuje editacni zamky podle stabilni identity lokalniho uzivatele
 */
final class EditorLockOwnerReleaser implements EditorLockOwnerReleaserInterface
{
    /**
     * Nastavuje databazovou hranici pro odstraneni vlastnenych zamku
     */
    public function __construct(private readonly Database $database) {}

    /**
     * Uvolnuje zamky vlastnene zadanym lokalnim uzivatelem
     */
    public function releaseOwnedByUser(int $userId): void
    {
        $this->database->transaction(fn(): int => $this->database->statement(
            'DELETE FROM admin_editor_lock WHERE owner_user_id = ?',
            [$userId],
        ));
    }
}
