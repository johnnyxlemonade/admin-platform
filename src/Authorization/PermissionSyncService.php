<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Framework\Database\Database;

/**
 * Synchronizuje opravneni ulozena v systemu
 */
final class PermissionSyncService
{
    public function __construct(
        private readonly PermissionCatalogRegistry $catalog,
        private readonly Database $database,
    ) {}

    public function sync(): PermissionSyncReport
    {
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $existing = [];
        foreach ($this->database->select('SELECT code,module_code,name_key FROM system_permission') as $row) {
            $existing[(string) $row['code']] = $row;
        }
        $defined = [];
        foreach ($this->catalog->all() as $definition) {
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
        $stale = array_values(array_diff(array_keys($existing), $defined));
        sort($stale);
        if ($stale !== []) {
            $placeholders = implode(',', array_fill(0, count($stale), '?'));
            $this->database->statement(
                'DELETE assignments FROM system_role_permission assignments INNER JOIN system_permission permission ON permission.id = assignments.permission_id WHERE permission.code IN (' . $placeholders . ')',
                $stale,
            );
            $this->database->statement(
                'DELETE assignments FROM system_user_permission assignments INNER JOIN system_permission permission ON permission.id = assignments.permission_id WHERE permission.code IN (' . $placeholders . ')',
                $stale,
            );
            $this->database->statement('DELETE FROM system_permission WHERE code IN (' . $placeholders . ')', $stale);
        }

        return new PermissionSyncReport($inserted, $updated, $unchanged, $stale);
    }

    /**
     * Synchronizuje jen deklarace jednoho prave instalovaneho modulu bez mazani ostatnich prav
     */
    public function syncModule(string $moduleCode): PermissionSyncReport
    {
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $existing = [];
        foreach ($this->database->select('SELECT code,module_code,name_key FROM system_permission WHERE module_code=?', [$moduleCode]) as $row) {
            $existing[(string) $row['code']] = $row;
        }
        foreach ($this->catalog->all() as $definition) {
            if ($definition->moduleCode() !== $moduleCode) {
                continue;
            }
            $row = $existing[$definition->code()] ?? null;
            if ($row === null) {
                $this->database->statement('INSERT INTO system_permission (code,module_code,name_key) VALUES (?,?,?)', [$definition->code(), $definition->moduleCode(), $definition->nameKey()]);
                $inserted++;
                continue;
            }
            if ($row['name_key'] !== $definition->nameKey()) {
                $this->database->statement('UPDATE system_permission SET module_code=?,name_key=? WHERE code=?', [$definition->moduleCode(), $definition->nameKey(), $definition->code()]);
                $updated++;
                continue;
            }
            $unchanged++;
        }

        return new PermissionSyncReport($inserted, $updated, $unchanged, []);
    }
}
