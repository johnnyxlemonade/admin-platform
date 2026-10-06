<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQueryValidator;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use PHPUnit\Framework\TestCase;

final class DataGridQueryValidatorTest extends TestCase
{
    public function testItNormalizesAValidQuery(): void
    {
        $query = (new DataGridQueryValidator())->validate($this->definition(), ['page' => '2', 'pageSize' => '25', 'sort' => 'email', 'direction' => 'desc', 'q' => ' user ', 'status' => 'active']);

        self::assertSame(2, $query->page());
        self::assertSame(25, $query->pageSize());
        self::assertSame('email', $query->sortKey());
        self::assertSame('desc', $query->sortDirection());
        self::assertSame('user', $query->search());
        self::assertSame('active', $query->filter('status'));
    }

    public function testItUsesTheDefaultAscendingSortWhenTheRequestDoesNotSelectSort(): void
    {
        $query = (new DataGridQueryValidator())->validate($this->definition(), []);

        self::assertSame('email', $query->sortKey());
        self::assertSame('asc', $query->sortDirection());
    }

    public function testItUsesTheDefaultDescendingSortWhenTheRequestDoesNotSelectSort(): void
    {
        $query = (new DataGridQueryValidator())->validate($this->definition(defaultSortDirection: 'desc'), []);

        self::assertSame('email', $query->sortKey());
        self::assertSame('desc', $query->sortDirection());
    }

    public function testItNormalizesExplicitAscendingAndDescendingSorts(): void
    {
        $validator = new DataGridQueryValidator();

        self::assertSame('asc', $validator->validate($this->definition(), ['sort' => 'email', 'direction' => 'asc'])->sortDirection());
        self::assertSame('desc', $validator->validate($this->definition(), ['sort' => 'email', 'direction' => 'desc'])->sortDirection());
    }

    public function testItUsesAscendingDirectionWhenAnExplicitSortOmitsDirection(): void
    {
        $query = (new DataGridQueryValidator())->validate($this->definition(), ['sort' => 'email']);

        self::assertSame('asc', $query->sortDirection());
    }

    /**
     * Umozni canonical views nad jinym filtrem nez je lifecycle status
     */
    public function testItExposesAnExplicitTypeViewFilter(): void
    {
        $typeFilter = new DataGridFilterDefinition('type', new StaticSelectOptionSource([
            new SelectOptionDefinition('image', 'Images'),
            new SelectOptionDefinition('document', 'Documents'),
        ]));
        $definition = new DataGridDefinition(
            id: 'media',
            columns: [new DataGridColumnDefinition('name', 'media.fields.name', 'name')],
            searchEnabled: true,
            filters: [
                $typeFilter,
                new DataGridFilterDefinition('status', new StaticSelectOptionSource([
                    new SelectOptionDefinition('active', 'Active'),
                    new SelectOptionDefinition('deleted', 'Deleted'),
                ])),
            ],
            defaultSortKey: 'name',
            defaultSortDirection: 'asc',
            defaultPageSize: 20,
            maximumPageSize: 100,
            viewFilterKey: 'type',
        );

        self::assertSame($typeFilter, $definition->viewFilter());
        self::assertSame('image', (new DataGridQueryValidator())->validate($definition, ['type' => 'image', 'status' => 'deleted'])->filter('type'));
    }

    public function testDefinitionRejectsAnUndeclaredDefaultSortKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DataGridDefinition(
            id: 'test',
            columns: [new DataGridColumnDefinition('email', 'test.fields.email', 'email')],
            searchEnabled: false,
            filters: [],
            defaultSortKey: 'name',
            defaultSortDirection: 'sideways',
            defaultPageSize: 20,
            maximumPageSize: 100,
        );
    }

    public function testDefinitionRejectsDuplicateSortableColumnSortKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DataGridDefinition(
            id: 'test',
            columns: [
                new DataGridColumnDefinition('user', 'test.fields.user', 'email'),
                new DataGridColumnDefinition('email', 'test.fields.email', 'email'),
            ],
            searchEnabled: false,
            filters: [],
            defaultSortKey: 'email',
            defaultSortDirection: 'asc',
            defaultPageSize: 20,
            maximumPageSize: 100,
        );
    }

    public function testDefinitionRejectsAnInvalidDefaultSortDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DataGridDefinition(
            id: 'test',
            columns: [new DataGridColumnDefinition('email', 'test.fields.email', 'email')],
            searchEnabled: false,
            filters: [],
            defaultSortKey: 'email',
            defaultSortDirection: 'sideways',
            defaultPageSize: 20,
            maximumPageSize: 100,
        );
    }

    public function testItRejectsInvalidPublicInput(): void
    {
        $validator = new DataGridQueryValidator();
        $rejected = 0;

        foreach ([['sort' => 'database_column'], ['sort' => 'email', 'direction' => 'sideways'], ['status' => 'unknown'], ['page' => '0'], ['pageSize' => '101']] as $input) {
            try {
                $validator->validate($this->definition(), $input);
                self::fail('Invalid DataGrid query was accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(5, $rejected);
    }

    private function definition(string $defaultSortDirection = 'asc'): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'test',
            columns: [new DataGridColumnDefinition('email', 'test.fields.email', 'email')],
            searchEnabled: true,
            filters: [new DataGridFilterDefinition('status', new StaticSelectOptionSource([new SelectOptionDefinition('active', 'Active'), new SelectOptionDefinition('inactive', 'Inactive')]))],
            defaultSortKey: 'email',
            defaultSortDirection: $defaultSortDirection,
            defaultPageSize: 20,
            maximumPageSize: 100,
        );
    }
}
