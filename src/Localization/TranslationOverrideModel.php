<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Admin\Platform\Migrations\CreateCoreTranslationOverrides;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Migration\MigrationStateRepository;
use Lemonade\Framework\Database\Model;

/**
 * Uklada pouze explicitni hodnoty, ktere prekryvaji package translation source
 */
final class TranslationOverrideModel extends Model
{
    protected string $table = 'system_translation_override';

    protected bool $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected array $allowedFields = ['locale', 'translation_group', 'translation_key', 'value'];

    private ?bool $schemaReady = null;

    /**
     * Sdili stav canonical migrace mezi runtime ctenim a client revision
     */
    public function __construct(
        DatabaseDriverInterface $database,
        private readonly MigrationStateRepository $migrations,
    ) {
        parent::__construct($database);
    }

    /**
     * @return list<string>
     */
    public function groups(string $locale): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        /** @var list<array{translation_group:string}> $rows */
        $rows = $this->query()
            ->select('translation_group')
            ->where('locale', $locale)
            ->orderBy('translation_group')
            ->getArray();

        return array_map(static fn(array $row): string => $row['translation_group'], $rows);
    }

    /**
     * @return array<string, string>
     */
    public function group(string $locale, string $group): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        /** @var list<array{translation_key:string,value:string}> $rows */
        $rows = $this->query()
            ->select(['translation_key', 'value'])
            ->where('locale', $locale)
            ->where('translation_group', $group)
            ->orderBy('translation_key')
            ->getArray();
        $overrides = [];
        foreach ($rows as $row) {
            $overrides[$row['translation_key']] = $row['value'];
        }

        return $overrides;
    }

    /**
     * Vraci existujici explicitni hodnotu nebo null bez ohledu na jeji delku
     */
    public function value(string $locale, string $group, string $key): ?string
    {
        if (!$this->schemaReady()) {
            return null;
        }

        /** @var array{value:string}|null $override */
        $override = $this->query()
            ->select('value')
            ->where('locale', $locale)
            ->where('translation_group', $group)
            ->where('translation_key', $key)
            ->first();

        return $override['value'] ?? null;
    }

    /**
     * Vytvori nebo aktualizuje explicitni hodnotu pod canonical identitou
     */
    public function saveValue(string $locale, string $group, string $key, string $value): bool
    {
        $this->requireSchema();

        /** @var array{id:int}|null $existing */
        $existing = $this->query()
            ->select('id')
            ->where('locale', $locale)
            ->where('translation_group', $group)
            ->where('translation_key', $key)
            ->first();
        if ($existing === null) {
            return $this->insert([
                'locale' => $locale,
                'translation_group' => $group,
                'translation_key' => $key,
                'value' => $value,
            ]) !== null;
        }

        return $this->update($existing['id'], ['value' => $value]);
    }

    /**
     * Odstrani explicitni hodnotu, aby se znovu uplatnil package source
     */
    public function remove(string $locale, string $group, string $key): bool
    {
        $this->requireSchema();

        return $this->query()
            ->where('locale', $locale)
            ->where('translation_group', $group)
            ->where('translation_key', $key)
            ->delete();
    }

    /**
     * Vraci nulu pro dosud nezmenenou kombinaci locale a group
     */
    public function revision(string $locale, string $group): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }

        $result = $this->db->query(
            'SELECT revision FROM system_translation_override_revision WHERE locale = ? AND translation_group = ?',
            [$locale, $group],
        );
        if (!$result instanceof DatabaseResultInterface) {
            throw new \RuntimeException('Unable to read translation override revision.');
        }

        $row = $result->row_array();

        return $row === null ? 0 : (int) $row['revision'];
    }

    /**
     * Zvysuje revizi v ramci mutacni transakce po uspesne zmene override
     */
    public function incrementRevision(string $locale, string $group): void
    {
        $this->requireSchema();

        $now = date('Y-m-d H:i:s');
        $updated = $this->db->query(
            'UPDATE system_translation_override_revision SET revision = revision + 1, updated_at = ? WHERE locale = ? AND translation_group = ?',
            [$now, $locale, $group],
        );
        if ($updated === false) {
            throw new \RuntimeException('Unable to update translation override revision.');
        }
        if ($this->db->affected_rows() > 0) {
            return;
        }

        $inserted = $this->db->query(
            'INSERT INTO system_translation_override_revision (locale,translation_group,revision,created_at,updated_at) VALUES (?,?,?,?,?)',
            [$locale, $group, 1, $now, $now],
        );
        if ($inserted === false) {
            throw new \RuntimeException('Unable to create translation override revision.');
        }
    }

    /**
     * Overuje jednou canonical migration ledger pred prvnim pristupem k novym tabulkam
     */
    private function schemaReady(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        try {
            return $this->schemaReady = in_array(
                CreateCoreTranslationOverrides::identifier(),
                $this->migrations->applied(),
                true,
            );
        } catch (DatabaseException) {
            return $this->schemaReady = false;
        }
    }

    /**
     * Zapis vyzaduje aplikovanou schema migraci
     */
    private function requireSchema(): void
    {
        if (!$this->schemaReady()) {
            throw new \LogicException('Translation override schema is not installed.');
        }
    }
}
