<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Persistence;

use Lemonade\Framework\Database\Database;

/**
 * Zprostredkuje data prefixu rout modulu
 */
final class ModuleRoutePrefixModel
{
    public function __construct(private readonly Database $database) {}

    /** @return array<string, array<string, string>> */
    public function all(): array
    {
        $prefixes = [];
        foreach ($this->database->select(
            'SELECT m.code, p.locale, p.prefix FROM system_module_route_prefix p JOIN system_module m ON m.id = p.module_id',
        ) as $row) {
            $moduleCode = $row['code'] ?? null;
            $locale = $row['locale'] ?? null;
            $prefix = $row['prefix'] ?? null;
            if (!is_string($moduleCode) || $moduleCode === '' || !is_string($locale) || $locale === '' || !is_string($prefix) || $prefix === '') {
                continue;
            }
            $prefixes[$moduleCode][$locale] = $prefix;
        }

        return $prefixes;
    }

    public function insertIfMissing(string $moduleCode, string $locale, string $prefix): bool
    {
        $now = date('Y-m-d H:i:s');

        return $this->database->statement(
            'INSERT INTO system_module_route_prefix(module_id, locale, prefix, created_at, updated_at) '
            . 'SELECT m.id, ?, ?, ?, ? FROM system_module m JOIN system_language l ON l.code = ? '
            . 'WHERE m.code = ? AND NOT EXISTS ('
            . 'SELECT 1 FROM system_module_route_prefix p WHERE p.module_id = m.id AND p.locale = ?'
            . ')',
            [$locale, $prefix, $now, $now, $locale, $moduleCode, $locale],
        ) > 0;
    }

    public function prefixFor(string $moduleCode, string $locale): ?string
    {
        $rows = $this->database->select(
            'SELECT p.prefix FROM system_module_route_prefix p JOIN system_module m ON m.id = p.module_id WHERE m.code = ? AND p.locale = ?',
            [$moduleCode, $locale],
        );
        $prefix = $rows[0]['prefix'] ?? null;

        return is_string($prefix) && $prefix !== '' ? $prefix : null;
    }

    /**
     * Vrati kod modulu, ktery vlastni prefix v konkretni lokalizaci
     */
    public function moduleFor(string $locale, string $prefix): ?string
    {
        $rows = $this->database->select(
            'SELECT m.code FROM system_module_route_prefix p JOIN system_module m ON m.id = p.module_id WHERE p.locale = ? AND p.prefix = ?',
            [$locale, $prefix],
        );
        $moduleCode = $rows[0]['code'] ?? null;

        return is_string($moduleCode) && $moduleCode !== '' ? $moduleCode : null;
    }

    /**
     * Overi, zda canonical content locale existuje pro seed module prefixu
     */
    public function localeExists(string $locale): bool
    {
        return $this->database->select('SELECT code FROM system_language WHERE code = ?', [$locale]) !== [];
    }
}
