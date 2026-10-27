<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Cms\CmsPublicationState;
use Lemonade\Cms\Content\CmsContentDefinition;
use Lemonade\Cms\Content\CmsContentQueries;
use Lemonade\Cms\Content\CmsDomainMapping;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Provadi standardni CMS DataGrid dotazy nad registrovanymi sloupci
 */
final readonly class CmsContentDataGrid
{
    /**
     * Nastavuje databazi a definici obsahu bez povinneho moduloveho adapteru
     */
    public function __construct(private Database $database, private CmsContentDefinition $content) {}

    /**
     * Nacte stranku s totoznym filtrovanim poctu a radku
     *
     * @return QueryPage<array<string,mixed>>
     */
    public function listForDataGrid(
        DataGridQuery $query,
        string $defaultLocale,
        ?int $ownerId = null,
        CmsDataGridDefinition $definition = new CmsDataGridDefinition(),
    ): QueryPage {
        $properties = $this->properties();
        $sorts = ['title' => 't.title', 'publishedAt' => 't.published_at', 'updatedAt' => 'a.updated_at', 'state' => 't.publication_state'];
        $select = ['t.locale', 't.title', 't.publication_state', 't.published_at'];
        $search = ['t.title', 'route.path'];
        foreach ($definition->columns as $column) {
            $sqlColumn = $properties[$column->property] ?? throw new InvalidArgumentException('Unknown CMS grid property: ' . $column->property);
            if (str_starts_with($sqlColumn, 't.')) {
                $select[] = $sqlColumn;
            }
            if ($column->sortable) {
                $sorts[$column->property] = $sqlColumn;
            }
            if ($column->searchable) {
                $search[] = $sqlColumn;
            }
        }
        $defaultSort = $sorts[$definition->defaultSort] ?? throw new InvalidArgumentException('Unknown CMS default sort.');
        $builder = $this->dataGridQuery($query, $query->filter('locale') ?? $defaultLocale, $ownerId, $definition, $properties, $search);
        $total = $builder->countAllResults();
        $rows = $builder->selectRaw(implode(', ', array_unique($select)))
            ->orderBy($sorts[$query->sortKey()] ?? $defaultSort, $query->sortDirection() === 'asc' ? 'ASC' : 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())->getArray();

        return new QueryPage($rows, $query->page(), $query->pageSize(), $total);
    }

    /**
     * Sklada spolecne JOINy a filtry vcetne vlastnika a soft-delete stavu
     *
     * @param array<string,string> $properties
     * @param list<string> $searchColumns
     */
    private function dataGridQuery(DataGridQuery $query, string $locale, ?int $ownerId, CmsDataGridDefinition $definition, array $properties, array $searchColumns): QueryBuilder
    {
        $projections = new CmsContentQueries($this->database, $this->content);
        $builder = $projections->withCanonicalRoute($projections->root()
            ->joinOn($this->content->translationTable . ' t', 't.' . $this->content->translationForeignKey, '=', 'a.id'))
            ->where('t.locale', $locale);
        if ($ownerId !== null) {
            $builder = $builder->where('a.owner_id', $ownerId);
        }
        $builder = $query->filter('status') === 'deleted'
            ? $builder->whereRaw('a.deleted_at IS NOT NULL')
            : $builder->where('a.deleted_at', null);
        if (trim($query->search()) !== '') {
            $builder = $builder->whereRaw(
                '(' . implode(' OR ', array_map(static fn(string $column): string => $column . ' LIKE ?', $searchColumns)) . ')',
                array_fill(0, count($searchColumns), '%' . trim($query->search()) . '%'),
            );
        }
        $states = ['new' => CmsPublicationState::New, 'draft' => CmsPublicationState::Draft, 'published' => CmsPublicationState::Published];
        $state = $states[$query->filter('state') ?? ''] ?? null;
        if ($state !== null) {
            $builder = $builder->where('t.publication_state', $state->value);
        }
        foreach ($definition->filters as $filter) {
            $column = $properties[$filter->property] ?? throw new InvalidArgumentException('Unknown CMS filter property: ' . $filter->property);
            $value = $query->filter($filter->property);
            if (in_array($value, $filter->values, true)) {
                $builder = $builder->where($column, $value);
            }
        }

        return $builder;
    }

    /**
     * Prelozi vyhradne registrovane PHP vlastnosti na duveryhodne SQL sloupce
     *
     * @return array<string,string>
     */
    private function properties(): array
    {
        $columns = (new CmsDomainMapping($this->content->dataClass))->columns();
        $properties = ['createdAt' => 'a.created_at'];
        foreach (['root' => 'a', 'localized' => 't'] as $scope => $alias) {
            foreach ($columns[$scope] as $property => $column) {
                $properties[$property] = $alias . '.' . $column;
            }
        }

        return $properties;
    }
}
