<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit\Models;

use JsonException;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\System\Audit\PersonalLoginAuditReaderInterface;
use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Cte nemennou historii auditnich udalosti pro prehled a dashboardy
 */
final class AuditLogModel extends Model implements PersonalLoginAuditReaderInterface
{
    protected string $table = 'system_audit_log';

    /**
     * Nacita auditni udalosti podle validovaneho DataGrid dotazu
     *
     * @return QueryPage<array{actor_email:string|null,actor_first_name:string|null,actor_last_name:string|null,actor_user_id:int|null,actor_key:string,actor_type:string,created_at:string,entity_key:string,entity_type:string,event_code:string,id:int,module_code:string,payload:array<string,mixed>}>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        $total = $this->filtered($query)->countAllResults();
        $sorts = [
            'createdAt' => 'a.created_at',
            'actor' => 'u.email',
            'event' => 'a.event_code',
            'module' => 'a.module_code',
            'entity' => 'a.entity_key',
        ];
        $direction = $query->sortDirection() === 'desc' ? 'DESC' : 'ASC';
        $rows = $this->filtered($query)
            ->select([
                'a.id',
                'a.event_code',
                'a.module_code',
                'a.entity_type',
                'a.entity_key',
                'a.actor_type',
                'a.actor_key',
                'a.actor_user_id',
                'a.payload',
                'a.created_at',
            ])
            ->selectRaw('u.first_name AS actor_first_name, u.last_name AS actor_last_name, u.email AS actor_email')
            ->orderBy($sorts[$query->sortKey()], $direction)
            ->orderBy('a.id', 'DESC')
            ->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())
            ->getArray();

        $data = array_map(fn(array $row): array => $this->normalizeRow($row), $rows);

        return new QueryPage($data, $query->page(), $query->pageSize(), $total);
    }

    /**
     * Zjistuje moduly, ktere maji alespon jednu auditni udalost
     *
     * @return list<string>
     */
    public function moduleCodes(): array
    {
        $rows = $this->query()
            ->from('system_audit_log a')
            ->select('a.module_code')
            ->groupBy('a.module_code')
            ->orderBy('a.module_code')
            ->getArray();

        return array_map(static fn(array $row): string => (string) $row['module_code'], $rows);
    }

    /**
     * Nacita posledni udalosti prihlaseni pro zadanou identitu
     *
     * @return list<array{created_at:string,payload:array<string,mixed>}>
     */
    public function recentLoginEvents(string $actorKey, int $limit): array
    {
        if ($actorKey === '' || $limit < 1) {
            return [];
        }

        $rows = $this->query()
            ->from('system_audit_log a')
            ->select(['a.created_at', 'a.payload'])
            ->where('a.event_code', 'system.authentication.login')
            ->where('a.actor_key', $actorKey)
            ->orderBy('a.created_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit($limit)
            ->getArray();

        return array_map(fn(array $row): array => [
            'created_at' => (string) $row['created_at'],
            'payload' => $this->payload((string) $row['payload']),
        ], $rows);
    }

    /**
     * Nacita nejnovejsi udalosti pro dashboardovy auditni widget
     *
     * @return list<array{actor_email:string|null,actor_first_name:string|null,actor_last_name:string|null,actor_user_id:int|null,actor_key:string,actor_type:string,created_at:string,entity_key:string,entity_type:string,event_code:string,id:int,module_code:string,payload:array<string,mixed>}>
     */
    public function recentForDashboard(int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = $this->query()
            ->from('system_audit_log a')
            ->select([
                'a.id',
                'a.event_code',
                'a.module_code',
                'a.entity_type',
                'a.entity_key',
                'a.actor_type',
                'a.actor_key',
                'a.actor_user_id',
                'a.payload',
                'a.created_at',
            ])
            ->selectRaw('u.first_name AS actor_first_name, u.last_name AS actor_last_name, u.email AS actor_email')
            ->join('system_user u', 'u.id = a.actor_user_id', 'LEFT')
            ->orderBy('a.created_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit($limit)
            ->getArray();

        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * Vytvori zaklad dotazu se jmennymi udaji aktera a filtrem modulu
     */
    private function filtered(DataGridQuery $query): QueryBuilder
    {
        $builder = $this->query()
            ->from('system_audit_log a')
            ->join('system_user u', 'u.id = a.actor_user_id', 'LEFT');
        $module = $query->filter('module');
        if ($module !== null) {
            $builder = $builder->where('a.module_code', $module);
        }

        return $builder;
    }

    /**
     * Normalizuje databazovy radek na kontrakt pouzivany prezentaci auditu
     *
     * @param array<string, mixed> $row
     * @return array{actor_email:string|null,actor_first_name:string|null,actor_last_name:string|null,actor_user_id:int|null,actor_key:string,actor_type:string,created_at:string,entity_key:string,entity_type:string,event_code:string,id:int,module_code:string,payload:array<string,mixed>}
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'event_code' => (string) $row['event_code'],
            'module_code' => (string) $row['module_code'],
            'entity_type' => (string) $row['entity_type'],
            'entity_key' => (string) $row['entity_key'],
            'actor_type' => (string) $row['actor_type'],
            'actor_key' => (string) $row['actor_key'],
            'actor_user_id' => $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            'actor_first_name' => $row['actor_first_name'] === null ? null : (string) $row['actor_first_name'],
            'actor_last_name' => $row['actor_last_name'] === null ? null : (string) $row['actor_last_name'],
            'actor_email' => $row['actor_email'] === null ? null : (string) $row['actor_email'],
            'payload' => $this->payload((string) $row['payload']),
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * Dekoduje payload udalosti a pri neplatnem JSON vraci prazdny payload
     *
     * @return array<string, mixed>
     */
    private function payload(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
