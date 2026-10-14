<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Framework\Database\Database;

/**
 * Synchronizuje opravneni ulozena v systemu
 */
final readonly class PermissionSyncService
{
    /** @var list<string> */
    private const array ADMINISTRATOR_EXCLUDED_PERMISSIONS = [
        'system.modules.view',
        'system.modules.install',
        'system.modules.enable',
        'system.modules.disable',
        'system.modules.manage_features',
    ];

    public function __construct(
        private PermissionCatalogRegistry $catalog,
        private Database $database,
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
        $this->removeStalePermissions($stale);

        $this->reconcileAdministratorPermissions();

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
        $defined = [];
        foreach ($this->database->select('SELECT code,module_code,name_key FROM system_permission WHERE module_code=?', [$moduleCode]) as $row) {
            $existing[(string) $row['code']] = $row;
        }
        foreach ($this->catalog->all() as $definition) {
            if ($definition->moduleCode() !== $moduleCode) {
                continue;
            }
            $defined[] = $definition->code();
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

        $stale = array_values(array_diff(array_keys($existing), $defined));
        sort($stale);
        $this->removeStalePermissions($stale);

        $this->reconcileAdministratorPermissions();

        return new PermissionSyncReport($inserted, $updated, $unchanged, $stale);
    }

    /**
     * Odstrani deklarace, ktere uz nejsou dostupne v aktualnim katalogu
     * @param list<string> $stale
     */
    private function removeStalePermissions(array $stale): void
    {
        if ($stale === []) {
            return;
        }

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

    /**
     * Sladi opravneni canonical role administrator s aktualne dostupnym katalogem v databazi
     */
    private function reconcileAdministratorPermissions(): void
    {
        $available = [];
        foreach ($this->database->select('SELECT code FROM system_permission') as $row) {
            $code = (string) $row['code'];
            if (!in_array($code, self::ADMINISTRATOR_EXCLUDED_PERMISSIONS, true)) {
                $available[$code] = true;
            }
        }

        $assigned = [];
        foreach ($this->database->select(
            'SELECT permission.code FROM system_role_permission assignments INNER JOIN system_role role ON role.id = assignments.role_id INNER JOIN system_permission permission ON permission.id = assignments.permission_id WHERE role.code = ?',
            ['admin'],
        ) as $row) {
            $assigned[(string) $row['code']] = true;
        }

        $toRemove = array_values(array_diff(array_keys($assigned), array_keys($available)));
        sort($toRemove);
        if ($toRemove !== []) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $this->database->statement(
                'DELETE assignments FROM system_role_permission assignments INNER JOIN system_role role ON role.id = assignments.role_id INNER JOIN system_permission permission ON permission.id = assignments.permission_id WHERE role.code = ? AND permission.code IN (' . $placeholders . ')',
                ['admin', ...$toRemove],
            );
        }

        $toAdd = array_values(array_diff(array_keys($available), array_keys($assigned)));
        sort($toAdd);
        if ($toAdd !== []) {
            $placeholders = implode(',', array_fill(0, count($toAdd), '?'));
            $this->database->statement(
                'INSERT INTO system_role_permission (role_id, permission_id) SELECT role.id, permission.id FROM system_role role INNER JOIN system_permission permission ON permission.code IN (' . $placeholders . ') WHERE role.code = ?',
                [...$toAdd, 'admin'],
            );
        }
    }
}
