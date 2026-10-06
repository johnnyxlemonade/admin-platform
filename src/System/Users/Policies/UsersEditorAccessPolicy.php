<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Policies;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Editor\Contract\EditorAccessPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorLockBypassPolicyInterface;

/**
 * Urci uzky self-service pristup a lock bypass pro vlastni profil uzivatele
 */
final class UsersEditorAccessPolicy implements EditorAccessPolicyInterface, EditorLockBypassPolicyInterface
{
    /**
     * Nastavuje authorization zdroj aktualniho uzivatele
     */
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * Povoli nacteni vlastniho profilu mimo bezne editorove opravneni
     */
    public function loadAccessDecision(int $id): ?bool
    {
        return $this->selfServiceDecision($id);
    }

    /**
     * Povoli ulozeni vlastniho profilu mimo bezne editorove opravneni
     */
    public function saveAccessDecision(int $id): ?bool
    {
        return $this->selfServiceDecision($id);
    }

    /**
     * Vraci explicitni self-service rozhodnuti jen pro aktualniho uzivatele
     */
    public function selfServiceDecision(?int $id): ?bool
    {
        return $id !== null && $this->authorization->currentUserId() === $id ? true : null;
    }

    /**
     * Povoli obejiti ciziho locku pouze pri editaci vlastniho profilu
     */
    public function canBypassForeignLock(int $id): bool
    {
        return $this->selfServiceDecision($id) === true;
    }

    /**
     * Overuje, zda muze uzivatel ulozit vlastni profil mimo standardni editor lock
     *
     * @param array<string, mixed> $payload
     */
    public function canSaveWithoutLock(int $id, array $payload): bool
    {
        if (!$this->canBypassForeignLock($id)) {
            return false;
        }

        $allowed = ['first_name', 'last_name', 'email', 'phone', 'active', 'local_password', 'version'];
        foreach ($payload as $field => $_value) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                return false;
            }
        }

        return isset($payload['active']) && (string) $payload['active'] === '1';
    }
}
