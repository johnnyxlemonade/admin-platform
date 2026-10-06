<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Query;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\DataGridDefinition;

/**
 * Overuje parametry dotazu datove tabulky
 */
final class DataGridQueryValidator
{
    /**
     * Overi klientsky vstup a vytvori dotaz povoleny definici gridu
     *
     * @param array<string, mixed> $input
     */
    public function validate(DataGridDefinition $definition, array $input): DataGridQuery
    {
        $page = $this->integer($input['page'] ?? 1, 'page');
        $pageSize = $this->integer($input['pageSize'] ?? $definition->defaultPageSize(), 'pageSize');
        if ($page < 1 || $pageSize < 1 || $pageSize > $definition->maximumPageSize()) {
            throw new InvalidArgumentException('Invalid pagination.');
        }
        $sortKey = (string) ($input['sort'] ?? $definition->defaultSortKey());
        $direction = array_key_exists('sort', $input)
            ? (string) ($input['direction'] ?? 'asc')
            : $definition->defaultSortDirection();
        if (!in_array($sortKey, $definition->sortKeys(), true)) {
            throw new InvalidArgumentException('Invalid sort.');
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Invalid sort direction.');
        }
        $search = trim((string) ($input['q'] ?? ''));
        if (!$definition->searchEnabled() && $search !== '') {
            throw new InvalidArgumentException('Search is not supported.');
        }
        $filters = [];
        foreach ($definition->filters() as $filter) {
            $key = $filter->key();
            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $filter->allowedValues(), true)) {
                throw new InvalidArgumentException('Invalid filter: ' . $key . '.');
            }
            $filters[$key] = $value;
        }

        return new DataGridQuery(
            page: $page,
            pageSize: $pageSize,
            sortKey: $sortKey,
            sortDirection: $direction,
            search: $search,
            filters: $filters,
        );
    }

    /**
     * Prevede vstup na cele cislo nebo ohlasi neplatny parametr
     */
    private function integer(mixed $value, string $name): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Invalid ' . $name . '.');
        }

        return (int) $value;
    }
}
