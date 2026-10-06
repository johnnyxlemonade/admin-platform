<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit;

/**
 * Zpristupnuje osobni historii prihlaseni bez zavislosti na auditnim modelu
 */
interface PersonalLoginAuditReaderInterface
{
    /**
     * Nacita posledni udalosti prihlaseni dane identity
     *
     * @return list<array{created_at:string,payload:array<string,mixed>}>
     */
    public function recentLoginEvents(string $actorKey, int $limit): array;
}
