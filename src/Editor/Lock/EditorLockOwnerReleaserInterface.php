<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

/**
 * Uvolnuje vsechny editacni zamky vlastnene lokalnim uzivatelem
 */
interface EditorLockOwnerReleaserInterface
{
    /**
     * Uvolnuje zamky vlastnene zadanym lokalnim uzivatelem
     */
    public function releaseOwnedByUser(int $userId): void;
}
