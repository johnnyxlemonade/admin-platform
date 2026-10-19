<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module\Contract;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Module\AdminModuleMetadata;

/**
 * Urcuje kontrakt pro administracni modul
 */
interface AdminModuleDefinitionInterface
{
    /**
     * Vraci stabilni kod administracniho modulu
     */
    public function code(): string;

    /**
     * Vraci navigacni a routovaci metadata modulu
     */
    public function adminMetadata(): AdminModuleMetadata;

    /**
     * Vraci prava deklarovana modulem
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array;

    /**
     * Vraci auditni udalosti vlastnene modulem
     *
     * @return list<AuditEventDefinition>
     */
    public function auditEventDefinitions(): array;
}
