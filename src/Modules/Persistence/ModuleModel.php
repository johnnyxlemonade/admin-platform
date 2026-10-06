<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Persistence;

use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\Exception\DatabaseException;

/**
 * Zprostredkuje data nainstalovanych modulu
 */
final class ModuleModel
{
    public function __construct(private readonly Database $database) {}

    /** @return array<string, bool> */
    public function enabledMap(): array
    {
        try {
            $rows = $this->database->select('SELECT code, enabled FROM system_module');
        } catch (DatabaseException $exception) {
            $message = strtolower($exception->getMessage());
            if (
                !str_contains($message, 'system_module')
                || (!str_contains($message, 'exist') && !str_contains($message, 'no such table'))
            ) {
                throw $exception;
            }

            return [];
        }

        $states = [];
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (is_string($code) && $code !== '') {
                $states[$code] = (int) ($row['enabled'] ?? 0) === 1;
            }
        }

        return $states;
    }

    public function setEnabled(string $code, bool $enabled): void
    {
        $this->database->statement('UPDATE system_module SET enabled = ? WHERE code = ?', [$enabled ? 1 : 0, $code]);
    }

    public function install(string $code): void
    {
        $this->database->statement(
            'INSERT INTO system_module(code, name, enabled, sort_order) VALUES (?, ?, 0, 0)',
            [$code, $code],
        );
    }
}
