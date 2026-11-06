<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Framework\Database\Database;

/**
 * Synchronizuje dostupny permission katalog bez automatickeho prideleni
 */
final readonly class PermissionSyncService
{
    public function __construct(
        private PermissionCatalogRegistry $catalog,
        private Database $database,
    ) {}

    /**
     * Zapise nove definice a vrati nedostupne ulozene kody bez mazani dat
     */
    public function sync(): PermissionSyncReport
    {
        return $this->syncDefinitions($this->catalog->all(), true);
    }

    /**
     * Zapise explicitni prispevek katalogu v transakci jeho vlastnika bez prideleni prav
     *
     * @param list<PermissionDefinition> $definitions
     */
    public function syncDefinitions(array $definitions, bool $reportStale = false): PermissionSyncReport
    {
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $existing = [];
        foreach ($this->database->select('SELECT code,module_code,name_key FROM system_permission') as $row) {
            $existing[(string) $row['code']] = $row;
        }
        $defined = [];
        foreach ($definitions as $definition) {
            $defined[] = $definition->code();
            $row = $existing[$definition->code()] ?? null;
            if ($row === null) {
                $this->database->statement('INSERT INTO system_permission (code,module_code,name_key) VALUES (?,?,?)', [$definition->code(), $definition->moduleCode(), $definition->nameKey()]);
                $inserted++;
                continue;
            }
            if ($row['module_code'] !== $definition->moduleCode() || $row['name_key'] !== $definition->nameKey()) {
                $this->database->statement('UPDATE system_permission SET module_code=?,name_key=? WHERE code=?', [$definition->moduleCode(), $definition->nameKey(), $definition->code()]);
                $updated++;
                continue;
            }
            $unchanged++;
        }
        $stale = $reportStale ? array_values(array_diff(array_keys($existing), $defined)) : [];
        sort($stale);

        return new PermissionSyncReport($inserted, $updated, $unchanged, $stale);
    }
}
