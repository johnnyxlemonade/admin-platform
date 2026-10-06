<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Persistence;

use Lemonade\Framework\Database\Database;

/**
 * Zprostredkuje data volitelnych funkci modulu
 */
final class ModuleFeatureModel
{
    public function __construct(private readonly Database $database) {}

    /** @return array<string, array<string, array{enabled:bool,provider:string|null}>> */
    public function all(): array
    {
        $rows = $this->database->select(
            'SELECT m.code, f.feature, f.enabled, f.provider FROM system_module_feature f JOIN system_module m ON m.id=f.module_id',
        );
        $features = [];
        foreach ($rows as $row) {
            $moduleCode = $row['code'] ?? null;
            $feature = $row['feature'] ?? null;
            if (!is_string($moduleCode) || $moduleCode === '' || !is_string($feature) || $feature === '') {
                continue;
            }
            $provider = $row['provider'] ?? null;
            $features[$moduleCode][$feature] = [
                'enabled' => (int) ($row['enabled'] ?? 0) === 1,
                'provider' => is_string($provider) ? $provider : null,
            ];
        }

        return $features;
    }

    public function enabled(string $moduleCode, string $feature): bool
    {
        return $this->database->select(
            'SELECT f.feature FROM system_module_feature f JOIN system_module m ON m.id=f.module_id WHERE m.code=? AND f.feature=? AND f.enabled=1',
            [$moduleCode, $feature],
        ) !== [];
    }

    public function provider(string $moduleCode, string $feature): ?string
    {
        $rows = $this->database->select(
            'SELECT f.provider FROM system_module_feature f JOIN system_module m ON m.id=f.module_id WHERE m.code=? AND f.feature=? LIMIT 1',
            [$moduleCode, $feature],
        );
        $provider = $rows[0]['provider'] ?? null;

        return is_string($provider) ? $provider : null;
    }

    public function insertToggleIfMissing(string $moduleCode, string $feature, bool $enabled): bool
    {
        return $this->database->statement(
            'INSERT INTO system_module_feature(module_id, feature, enabled, provider) '
            . 'SELECT m.id, ?, ?, NULL FROM system_module m '
            . 'WHERE m.code=? AND NOT EXISTS ('
            . 'SELECT 1 FROM system_module_feature f WHERE f.module_id=m.id AND f.feature=?'
            . ')',
            [$feature, $enabled ? 1 : 0, $moduleCode, $feature],
        ) > 0;
    }

    public function setEnabled(string $moduleCode, string $feature, bool $enabled): bool
    {
        return $this->database->statement(
            'UPDATE system_module_feature SET enabled=? '
            . 'WHERE module_id=(SELECT id FROM system_module WHERE code=?) AND feature=?',
            [$enabled ? 1 : 0, $moduleCode, $feature],
        ) > 0;
    }
}
