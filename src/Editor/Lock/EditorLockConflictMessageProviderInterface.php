<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

/**
 * Urcuje operace, ktere muze editor vyzadovat od editorlockconflictmessageprovider
 */
interface EditorLockConflictMessageProviderInterface
{
    /**
     * Zpracovava hodnotu lockconflictmessage v konfiguraci editoru
     */
    public function editorOpenLockConflictMessage(int $entityId, EditorLockOwner $owner): ?EditorLockConflictMessage;
}
