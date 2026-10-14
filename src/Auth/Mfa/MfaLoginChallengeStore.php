<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use Lemonade\Admin\Auth\InternalAdminDestination;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Uklada kratkodoby neautentizovany MFA krok lokalniho prihlaseni
 */
final readonly class MfaLoginChallengeStore
{
    private const string SESSION_KEY = 'admin.auth.mfa.challenge';
    private const int TTL_SECONDS = 300;

    public function __construct(
        private SessionInterface $session,
        private AdminRoutingConfiguration $routing,
    ) {}

    /**
     * Zalozi challenge pouze s bezpecnou interní cilovou cestou
     */
    public function begin(int $userId, string $kind, ?string $intendedPath): void
    {
        $this->session->set(self::SESSION_KEY, [
            'userId' => $userId,
            'kind' => $kind,
            'issuedAt' => time(),
            'intendedPath' => InternalAdminDestination::isSafe($intendedPath, $this->routing) ? $intendedPath : null,
        ]);
    }

    /**
     * Vrati platny challenge nebo zahodi expirovany a poskozeny stav
     *
     * @return array{userId:int,kind:string,intendedPath:string|null}|null
     */
    public function current(): ?array
    {
        $value = $this->session->get(self::SESSION_KEY);
        if (!is_array($value) || !is_int($value['userId'] ?? null) || !is_string($value['kind'] ?? null) || !is_int($value['issuedAt'] ?? null) || time() - $value['issuedAt'] > self::TTL_SECONDS) {
            $this->clear();
            return null;
        }

        return [
            'userId' => $value['userId'],
            'kind' => $value['kind'],
            'intendedPath' => InternalAdminDestination::isSafe($value['intendedPath'] ?? null, $this->routing) ? $value['intendedPath'] : null,
        ];
    }

    /**
     * Odstrani challenge pred zalozenim finální identity session
     */
    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }
}
