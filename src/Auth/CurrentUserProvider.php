<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Framework\Database\Database;

/**
 * Poskytuje aktualniho uzivatele administrace
 */
final class CurrentUserProvider implements CurrentPrincipalProviderInterface
{
    private bool $currentUserResolved = false;

    private ?AuthenticatedUser $resolvedCurrentUser = null;

    private ?int $resolvedSessionUserId = null;

    private bool $currentPrincipalResolved = false;

    private ?AdminPrincipalInterface $resolvedCurrentPrincipal = null;

    /**
     * Nastavi sluzby pro nacteni prihlaseneho uzivatele
     */
    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly Database $database,
    ) {}

    /**
     * Vrati aktivniho uzivatele odpovidajiciho aktualni relaci
     */
    public function currentUser(): ?AuthenticatedUser
    {
        $id = $this->authentication->sessionUserId();
        if ($this->currentUserResolved && $id === $this->resolvedSessionUserId) {
            return $this->resolvedCurrentUser;
        }

        $this->invalidate();
        $this->currentUserResolved = true;
        $this->resolvedSessionUserId = $id;
        if ($id === null) {
            return null;
        }

        $rows = $this->database->select(
            'SELECT id, email, first_name, last_name FROM system_user WHERE id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1',
            [$id],
        );
        if ($rows === []) {
            return null;
        }

        return $this->resolvedCurrentUser = new AuthenticatedUser(
            id: (int) $rows[0]['id'],
            email: (string) $rows[0]['email'],
            firstName: isset($rows[0]['first_name']) ? (string) $rows[0]['first_name'] : null,
            lastName: isset($rows[0]['last_name']) ? (string) $rows[0]['last_name'] : null,
        );
    }

    /**
     * Vrati lokalni principal aktualniho uzivatele
     */
    public function currentPrincipal(): ?AdminPrincipalInterface
    {
        $user = $this->currentUser();
        if ($user !== null) {
            if ($this->currentPrincipalResolved) {
                return $this->resolvedCurrentPrincipal;
            }
            $this->currentPrincipalResolved = true;

            return $this->resolvedCurrentPrincipal = new LocalAdminPrincipal($user);
        }
        $this->currentPrincipalResolved = true;
        $this->resolvedCurrentPrincipal = null;
        return null;
    }

    /**
     * Zahodi hodnoty ulozene pro aktualni relaci
     */
    public function invalidate(): void
    {
        $this->currentUserResolved = false;
        $this->resolvedCurrentUser = null;
        $this->resolvedSessionUserId = null;
        $this->currentPrincipalResolved = false;
        $this->resolvedCurrentPrincipal = null;
    }
}
