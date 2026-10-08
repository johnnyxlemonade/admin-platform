<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation\Models;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\Presentation\MediaCategory;
use Lemonade\Framework\Database\Model;
use Lemonade\Image\ImageOriginalMetadata;
use RuntimeException;

/**
 * Uklada canonical metadata souboru prirazeneho k zaznamu a jeho usage v Adminu
 */
final class AdminFileModel extends Model
{
    protected string $table = 'system_file';

    protected bool $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected array $allowedFields = [
        'module_code',
        'entity_id',
        'usage',
        'kind',
        'media_category',
        'asset_id',
        'source_version',
        'storage_path',
        'original_filename',
        'display_name',
        'caption',
        'extension',
        'mime_type',
        'file_size',
        'width',
        'height',
        'sort_order',
    ];

    /**
     * Nacte stranku souboru pro centralni management bez lookupu vlastniku nebo storage
     *
     * @return QueryPage<array<string,mixed>>
     */
    public function listForManagement(DataGridQuery $query): QueryPage
    {
        $type = $query->filter('type');
        $module = $query->filter('module');
        $filtered = $this->managementQuery($query->search(), $type, $module);
        $total = $filtered->countAllResults();
        $sorts = [
            'size' => 'f.file_size',
            'createdAt' => 'f.created_at',
            'module' => 'f.module_code',
        ];
        $direction = $query->sortDirection() === 'desc' ? 'DESC' : 'ASC';
        $itemsQuery = $this->managementQuery($query->search(), $type, $module)
            ->select(['f.id', 'f.module_code', 'f.entity_id', 'f.usage', 'f.kind', 'f.media_category', 'f.original_filename', 'f.display_name', 'f.caption', 'f.extension', 'f.mime_type', 'f.file_size', 'f.width', 'f.height', 'f.created_at'])
            ->when(
                $query->sortKey() === 'name',
                static fn($builder) => $builder
                    ->selectRaw('COALESCE(f.display_name, f.original_filename) AS management_sort_name')
                    ->orderBy('management_sort_name', $direction),
            )
            ->when(
                $query->sortKey() === 'kind',
                static fn($builder) => $builder
                    ->orderBy('f.media_category', $direction),
            )
            ->when(
                array_key_exists($query->sortKey(), $sorts),
                static fn($builder) => $builder->orderBy($sorts[$query->sortKey()], $direction),
            );
        $items = $itemsQuery
            ->orderBy('f.id', 'ASC')
            ->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())
            ->getArray();

        return new QueryPage($items, $query->page(), $query->pageSize(), $total);
    }

    /**
     * Nacte existujici soubor pro centralni management mutaci
     *
     * @return array<string,mixed>|null
     */
    public function findForManagement(int $id): ?array
    {
        return $this->query()->where('id', $id)->first();
    }

    /**
     * Nacte lifecycle metadata vybranych souboru jednim dotazem pro hromadnou mutaci
     *
     * @param list<int> $ids
     * @return array<int,array<string,mixed>>
     */
    public function findManyForManagement(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->query()
            ->whereIn('id', $ids)
            ->getArray();
        $files = [];
        foreach ($rows as $row) {
            $files[(int) $row['id']] = $row;
        }

        return $files;
    }

    /**
     * Nacte existujici moduly a usage pro filtry jednim databaseovym dotazem
     *
     * @return list<array{module_code:string,usage:string}>
     */
    public function managementFilterValues(): array
    {
        /** @var list<array{module_code:string,usage:string}> $values */
        $values = $this->query()
            ->select(['module_code', 'usage'])
            ->groupBy('module_code')
            ->groupBy('usage')
            ->orderBy('module_code', 'ASC')
            ->orderBy('usage', 'ASC')
            ->getArray();

        return $values;
    }

    /**
     * Fyzicky odstrani metadata souboru po uspesnem smazani jeho canonical originalu
     */
    public function removeForManagement(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Hleda nejnovejsi aktivni file identity pro single slot podle modulu, vlastnika a usage
     *
     * @return array{id:int,module_code:string,entity_id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null}|null
     */
    public function findForEntity(string $module, int $entityId, string $usage): ?array
    {
        /** @var array{id:int,module_code:string,entity_id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null}|null $file */
        $file = $this->query()
            ->select(['id', 'module_code', 'entity_id', 'usage', 'kind', 'asset_id', 'source_version', 'storage_path', 'original_filename', 'display_name', 'caption', 'extension', 'mime_type', 'file_size', 'width', 'height'])
            ->where('module_code', $module)
            ->where('entity_id', $entityId)
            ->where('usage', $usage)
            ->orderBy('id', 'DESC')
            ->first();

        return $file;
    }

    /**
     * Hleda aktivni image identity pro single slot bez zamichani budouciho document usage
     *
     * @return array<string, mixed>|null
     */
    public function findActiveImageForEntity(string $module, int $entityId, string $usage): ?array
    {
        return $this->query()
            ->where('module_code', $module)
            ->where('entity_id', $entityId)
            ->where('usage', $usage)
            ->where('kind', 'image')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Hleda aktivni image file podle public identity a vlastniciho modulu
     *
     * @return array<string, mixed>|null
     */
    public function findActiveImage(string $module, int $id): ?array
    {
        return $this->query()
            ->where('id', $id)
            ->where('module_code', $module)
            ->where('kind', 'image')
            ->first();
    }

    /**
     * Prepina aktivni image source na novou Framework asset a immutable source version
     */
    public function updateImageSource(int $id, string $assetId, string $sourceVersion, \Lemonade\Image\ImageOriginalMetadata $metadata): void
    {
        $this->update($id, [
            'asset_id' => $assetId,
            'source_version' => $sourceVersion,
            'original_filename' => $metadata->originalFilename(),
            'extension' => $metadata->format()->extension(),
            'mime_type' => $metadata->format()->mimeType(),
            'media_category' => MediaCategory::classify($metadata->format()->mimeType())->value,
            'file_size' => $metadata->size(),
            'width' => $metadata->width(),
            'height' => $metadata->height(),
        ]);
    }

    /**
     * Vraci nejnovejsi aktivni file identity pro single sloty jednim databazovym dotazem
     *
     * @param list<int> $entityIds
     * @return array<int, array{id:int,module_code:string,entity_id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null}>
     */
    public function findForEntities(string $module, array $entityIds, string $usage): array
    {
        if ($entityIds === []) {
            return [];
        }

        $rows = $this->query()
            ->select(['id', 'module_code', 'entity_id', 'usage', 'kind', 'original_filename', 'display_name', 'caption', 'extension', 'mime_type', 'file_size', 'width', 'height'])
            ->where('module_code', $module)
            ->whereIn('entity_id', $entityIds)
            ->where('usage', $usage)
            ->where('kind', 'image')
            ->orderBy('id', 'DESC')
            ->getArray();

        $files = [];
        foreach ($rows as $row) {
            if (isset($files[(int) $row['entity_id']])) {
                continue;
            }

            $files[(int) $row['entity_id']] = $this->file($row);
        }

        return $files;
    }

    /**
     * Vytvari prazdnou stabilni file identity pred ulozenim originalu
     */
    public function createIdentity(string $module, int $entityId, string $usage, string $kind): int
    {
        $id = $this->insert([
            'module_code' => $module,
            'entity_id' => $entityId,
            'usage' => $usage,
            'kind' => $kind,
            'media_category' => MediaCategory::Other->value,
            'storage_path' => null,
            'original_filename' => '',
            'display_name' => null,
            'caption' => null,
            'extension' => '',
            'mime_type' => '',
            'file_size' => 0,
            'width' => null,
            'height' => null,
            'sort_order' => $this->nextSortOrder($module, $entityId, $usage),
        ]);
        if (!is_int($id) && !is_string($id)) {
            throw new RuntimeException('File persistence did not return an identifier.');
        }

        return (int) $id;
    }

    /**
     * Vytvori persisted generic file po frameworkove finalizaci
     */
    public function createFile(string $module, int $entityId, string $usage, string $originalFilename, string $extension, string $mimeType, int $fileSize, string $storagePath): int
    {
        return (int) $this->insert([
            'module_code' => $module,
            'entity_id' => $entityId,
            'usage' => $usage,
            'kind' => 'file',
            'storage_path' => $storagePath,
            'original_filename' => $originalFilename,
            'display_name' => null,
            'caption' => null,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'media_category' => MediaCategory::classify($mimeType)->value,
            'file_size' => $fileSize,
            'width' => null,
            'height' => null,
            'sort_order' => $this->nextSortOrder($module, $entityId, $usage),
        ]);
    }

    /**
     * Nahradi metadata a canonical storage reference existujiciho generic single slotu.
     */
    public function updateFile(int $id, string $originalFilename, string $extension, string $mimeType, int $fileSize, string $storagePath): void
    {
        $this->update($id, [
            'storage_path' => $storagePath,
            'original_filename' => $originalFilename,
            'display_name' => null,
            'caption' => null,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'media_category' => MediaCategory::classify($mimeType)->value,
            'file_size' => $fileSize,
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * Nacte soubory jednoho owner usage v canonical poradi
     *
     * @return list<array<string,mixed>>
     */
    public function listForEntity(string $module, int $entityId, string $usage): array
    {
        return $this->query()->where('module_code', $module)->where('entity_id', $entityId)->where('usage', $usage)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->getArray();
    }

    /**
     * Nacte presentation metadata souboru pro vice usage jednoho vlastnika jednim dotazem
     *
     * @param list<string> $usages
     * @return list<array{id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null,sort_order:int}>
     */
    public function listForEntityUsages(string $module, int $entityId, array $usages): array
    {
        if ($usages === []) {
            return [];
        }

        /** @var list<array{id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null,sort_order:int}> $files */
        $files = $this->query()
            ->select([
                'id',
                'usage',
                'kind',
                'original_filename',
                'display_name',
                'caption',
                'extension',
                'mime_type',
                'file_size',
                'width',
                'height',
                'sort_order',
            ])
            ->where('module_code', $module)
            ->where('entity_id', $entityId)
            ->whereIn('usage', $usages)
            ->orderBy('usage', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->getArray();

        return $files;
    }

    /**
     * Overi zda file patri do jednoho explicitniho targetu
     *
     * @return array<string,mixed>|null
     */
    public function findForTarget(string $module, int $entityId, string $usage, int $fileId): ?array
    {
        return $this->query()->where('id', $fileId)->where('module_code', $module)->where('entity_id', $entityId)->where('usage', $usage)->first();
    }

    /**
     * Ulozi pouze presentation nazev konkretni file identity bez zmeny originalu
     */
    public function rename(int $fileId, string $displayName): void
    {
        $this->update($fileId, ['display_name' => $displayName]);
    }

    /**
     * Ulozi cele deterministicke poradi overene kolekce
     *
     * @param list<int> $fileIds
     */
    public function reorder(string $module, int $entityId, string $usage, array $fileIds): void
    {
        foreach ($fileIds as $sortOrder => $fileId) {
            $this->query()->where('id', $fileId)->where('module_code', $module)->where('entity_id', $entityId)->where('usage', $usage)->set(['sort_order' => $sortOrder])->update();
        }
    }

    /**
     * Vypocita dalsi pozici kolekce
     */
    private function nextSortOrder(string $module, int $entityId, string $usage): int
    {
        $row = $this->query()->selectRaw('COALESCE(MAX(sort_order), -1) AS max_sort_order')->where('module_code', $module)->where('entity_id', $entityId)->where('usage', $usage)->first();

        return (int) ($row['max_sort_order'] ?? -1) + 1;
    }

    /**
     * Aktualizuje metadata ulozeneho originalu pri zachovani stabilni file identity
     */
    public function updateMetadata(int $id, ImageOriginalMetadata $metadata): void
    {
        $this->update($id, [
            'original_filename' => $metadata->originalFilename(),
            'extension' => $metadata->format()->extension(),
            'mime_type' => $metadata->format()->mimeType(),
            'media_category' => MediaCategory::classify($metadata->format()->mimeType())->value,
            'file_size' => $metadata->size(),
            'width' => $metadata->width(),
            'height' => $metadata->height(),
        ]);
    }

    /**
     * Uklada volitelny presentation nazev a popisek bez zmeny storage identity nebo variant cache
     */
    public function updatePresentation(int $id, ?string $displayName, ?string $caption): void
    {
        $this->update($id, [
            'display_name' => $displayName,
            'caption' => $caption,
        ]);
    }

    /**
     * Soft-delete oznaci file metadata bez mazani originalu ze storage
     */
    public function remove(int $id): void
    {
        $this->delete($id);
    }

    /**
     * Normalizuje databazovy radek na stabilni file metadata contract
     *
     * @param array<string, mixed> $row
     * @return array{id:int,module_code:string,entity_id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null}
     */
    private function file(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'module_code' => (string) $row['module_code'],
            'entity_id' => (int) $row['entity_id'],
            'usage' => (string) $row['usage'],
            'kind' => (string) $row['kind'],
            'original_filename' => (string) $row['original_filename'],
            'display_name' => $row['display_name'] === null ? null : (string) $row['display_name'],
            'caption' => $row['caption'] === null ? null : (string) $row['caption'],
            'extension' => (string) $row['extension'],
            'mime_type' => (string) $row['mime_type'],
            'file_size' => (int) $row['file_size'],
            'width' => $row['width'] === null ? null : (int) $row['width'],
            'height' => $row['height'] === null ? null : (int) $row['height'],
        ];
    }

    /**
     * Sestavuje allowlistovany management dotaz nad databazovym katalogem souboru
     */
    private function managementQuery(string $search, ?string $type, ?string $module): \Lemonade\Framework\Database\QueryBuilder
    {
        $query = $this->query()->from('system_file f');
        $category = MediaCategory::tryFrom((string) $type);
        if ($category !== null) {
            $query = $query->where('f.media_category', $category->value);
        }
        if ($module !== null && $module !== '') {
            $query = $query->where('f.module_code', $module);
        }
        if ($search !== '') {
            $query = $query->whereRaw('(f.display_name LIKE ? OR f.original_filename LIKE ?)', ['%' . $search . '%', '%' . $search . '%']);
        }

        return $query;
    }
}
