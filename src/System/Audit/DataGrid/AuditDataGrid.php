<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit\DataGrid;

use Lemonade\Admin\DataGrid\Cell\DateTimeCell;
use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Audit\AuditEventPresenter;
use Lemonade\Admin\System\Audit\Models\AuditLogModel;

/**
 * Poskytuje read-only DataGrid nad historii systemoveho auditu
 */
final class AuditDataGrid implements DataGridProviderInterface
{
    public function __construct(
        private readonly AuditLogModel $audit,
        private readonly AuditEventPresenter $presenter,
    ) {}

    /**
     * Deklaruje sloupce, filtr modulu a razeni auditniho prehledu
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'audit',
            columns: [
                new DataGridColumnDefinition(
                    key: 'createdAt',
                    translationKey: 'audit.fields.created_at',
                    sortKey: 'createdAt',
                ),
                new DataGridColumnDefinition(
                    key: 'actor',
                    translationKey: 'audit.fields.actor',
                    sortKey: 'actor',
                ),
                new DataGridColumnDefinition(
                    key: 'event',
                    translationKey: 'audit.fields.action',
                    sortKey: 'event',
                ),
                new DataGridColumnDefinition(
                    key: 'module',
                    translationKey: 'audit.fields.module',
                    sortKey: 'module',
                    class: 'd-none d-lg-table-cell',
                ),
                new DataGridColumnDefinition(
                    key: 'entity',
                    translationKey: 'audit.fields.entity',
                    sortKey: 'entity',
                    class: 'd-none d-xl-table-cell',
                ),
            ],
            searchEnabled: false,
            filters: [
                new DataGridFilterDefinition(
                    key: 'module',
                    optionSource: new StaticSelectOptionSource(options: $this->moduleOptions()),
                ),
            ],
            defaultSortKey: 'createdAt',
            defaultSortDirection: 'desc',
            defaultPageSize: 25,
            maximumPageSize: 100,
        );
    }

    public function permission(): string
    {
        return 'system.audit.view';
    }

    /**
     * Prevede stranku auditnich zaznamu na typed radky DataGridu
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $list = $this->audit->listForDataGrid($query);

        return new DataGridResult(
            items: $this->rows($list->items()),
            page: $list->page(),
            perPage: $list->perPage(),
            total: $list->total(),
        );
    }

    /**
     * Zjistuje kody modulu dostupne pro filtr prehledu
     *
     * @return list<string>
     */
    public function moduleCodes(): array
    {
        return $this->audit->moduleCodes();
    }

    /**
     * Sklada lokalizovane volby filtru z modulu zaznamenanych v auditu
     *
     * @return list<SelectOptionDefinition>
     */
    public function moduleOptions(): array
    {
        return array_map(
            fn(string $code): SelectOptionDefinition => new SelectOptionDefinition(
                value: $code,
                label: $this->moduleLabel($code),
            ),
            $this->moduleCodes(),
        );
    }

    /**
     * Prevadi auditni zaznamy na zobrazitelne radky DataGridu
     *
     * @param list<array{actor_email:string|null,actor_first_name:string|null,actor_last_name:string|null,actor_user_id:int|null,actor_key:string,actor_type:string,created_at:string,entity_key:string,entity_type:string,event_code:string,id:int,module_code:string,payload:array<string,mixed>}> $events
     * @return list<DataGridRowDefinition>
     */
    private function rows(array $events): array
    {
        $rows = [];
        foreach ($events as $event) {
            $rows[] = new DataGridRowDefinition(
                id: $event['id'],
                cells: [
                    'createdAt' => new DateTimeCell(value: $event['created_at']),
                    'actor' => new ScalarCell(value: $this->presenter->actor($event)),
                    'event' => new ScalarCell(value: $this->presenter->description($event['event_code'], $event['payload'])),
                    'module' => new ScalarCell(value: $this->moduleLabel($event['module_code'])),
                    'entity' => new ScalarCell(value: $this->presenter->entityLabel($event['event_code'], $event['entity_key'], $event['payload'])),
                ],
            );
        }

        return $rows;
    }

    /**
     * Deleguje lokalizaci nazvu modulu na centralni presenter auditu
     */
    private function moduleLabel(string $moduleCode): string
    {
        return $this->presenter->moduleLabel($moduleCode);
    }
}
