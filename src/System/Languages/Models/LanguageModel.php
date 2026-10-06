<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Models;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Zprostredkuje persistenci jazyku a projekce pro DataGrid
 */
final class LanguageModel extends Model
{
    protected string $table = 'system_language';

    protected bool $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected array $allowedFields = ['code', 'name', 'flag_code', 'enabled', 'is_default', 'sort_order'];

    /**
     * Mapuje DataGrid dotaz na vyhledavani, filtr stavu a stabilni razeni jazyku
     *
     * @return QueryPage<array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int}>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        $filtered = $this->filtered($query->search(), $query->filter('status'));
        $sorts = ['name' => 'name', 'code' => 'code', 'default' => 'is_default', 'status' => 'enabled', 'sortOrder' => 'sort_order'];
        $direction = $query->sortDirection() === 'desc' ? 'DESC' : 'ASC';
        /**
         * @var list<array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int}> $data
         */
        $data = $filtered
            ->select(['id', 'code', 'name', 'flag_code', 'enabled', 'is_default', 'sort_order'])
            ->orderBy($sorts[$query->sortKey()], $direction)
            ->orderBy('code', 'ASC')
            ->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())
            ->getArray();

        return new QueryPage($data, $query->page(), $query->pageSize(), $this->filtered($query->search(), $query->filter('status'))->countAllResults());
    }

    /**
     * Nacita detailovy read model podle interniho identifikatoru
     */
    public function findLanguage(int $id): ?LanguageRecord
    {
        /**
         * @var array<string, mixed>|null $language
         */
        $language = $this->query()
            ->select(['id', 'code', 'name', 'flag_code', 'enabled', 'is_default', 'sort_order'])
            ->where('id', $id)
            ->first();

        return $language === null ? null : LanguageRecord::fromRow($language);
    }

    /**
     * Overuje obsazenost business klice code pred zalozenim jazyka
     */
    public function codeExists(string $code): bool
    {
        return $this->query()
            ->whereRaw('LOWER(code) = ?', [self::normalizeCode($code)])
            ->exists();
    }

    /**
     * Normalizuje kod jazyka podle canonical locale kontraktu
     */
    public static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    /**
     * Vytvari jazyk bez vychoziho priznaknu, ktery urcuje samostatna service operace
     */
    public function createLanguage(string $code, string $name, string $flagCode, bool $enabled, int $sortOrder): int
    {
        return (int) $this->insert(['code' => $code, 'name' => $name, 'flag_code' => $flagCode, 'enabled' => $enabled ? 1 : 0, 'is_default' => 0, 'sort_order' => $sortOrder]);
    }

    public function updateLanguage(int $id, string $name, string $flagCode, int $sortOrder): void
    {
        $this->update($id, ['name' => $name, 'flag_code' => $flagCode, 'sort_order' => $sortOrder]);
    }

    public function updateEnabled(int $id, bool $enabled): void
    {
        $this->update($id, ['enabled' => $enabled ? 1 : 0]);
    }

    /**
     * Odstranuje dosavadni vychozi priznaky pred nastavenim noveho defaultu
     */
    public function clearDefaults(): void
    {
        $this->query()->where('is_default', 1)->set(['is_default' => 0, 'updated_at' => date('Y-m-d H:i:s')])->update();
    }

    /**
     * Nastavuje vychozi priznaky na zvolenem zaznamu v ramci service transakce
     */
    public function setDefault(int $id): void
    {
        $this->update($id, ['is_default' => 1]);
    }

    /**
     * Pocita enabled zaznamy oznacene jako vychozi pro kontrolu domainoveho invariantu
     */
    public function enabledDefaultCount(): int
    {
        return $this->query()->where('enabled', 1)->where('is_default', 1)->countAllResults();
    }

    /**
     * Omezuje dotaz na stav a textove hledani ve jmene nebo business klici code
     */
    private function filtered(string $search, ?string $status): QueryBuilder
    {
        $query = $this->query();
        if ($status === 'enabled') {
            $query = $query->where('enabled', 1);
        } elseif ($status === 'disabled') {
            $query = $query->where('enabled', 0);
        }
        if ($search !== '') {
            $query = $query->whereRaw('(name LIKE ? OR code LIKE ?)', ['%' . $search . '%', '%' . $search . '%']);
        }

        return $query;
    }
}
