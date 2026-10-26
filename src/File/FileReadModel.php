<?php

declare(strict_types=1);

namespace Lemonade\Admin\File;

use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Nacita typovana metadata z canonical katalogu system_file
 */
final class FileReadModel extends Model
{
    protected string $table = 'system_file';

    /**
     * Vrati nejnovejsi soubor jednoho owner usage
     */
    public function findForEntity(
        string $moduleCode,
        int $entityId,
        string $usage,
        ?string $kind = null,
    ): ?FileMetadata {
        $row = $this->forEntityUsage($moduleCode, $entityId, $usage, $kind)
            ->orderBy('id', 'DESC')
            ->first();

        return $row === null ? null : $this->metadata($row);
    }

    /**
     * Vrati soubor podle jeho stabilni identity
     */
    public function findFile(int $fileId, ?string $kind = null): ?FileMetadata
    {
        $query = $this->query()->where('id', $fileId);
        if ($kind !== null) {
            $query = $query->where('kind', $kind);
        }
        $row = $query->first();

        return $row === null ? null : $this->metadata($row);
    }

    /**
     * Vrati soubor podle verejne identity a vlastniciho modulu
     */
    public function findById(string $moduleCode, int $fileId, ?string $kind = null): ?FileMetadata
    {
        $query = $this->query()
            ->where('id', $fileId)
            ->where('module_code', $moduleCode);
        if ($kind !== null) {
            $query = $query->where('kind', $kind);
        }
        $row = $query->first();

        return $row === null ? null : $this->metadata($row);
    }

    /**
     * Vrati nejnovnejsi soubor stejneho usage pro vice entit jednim dotazem
     *
     * @param list<int> $entityIds
     * @return array<int,FileMetadata>
     */
    public function findForEntities(
        string $moduleCode,
        array $entityIds,
        string $usage,
        ?string $kind = null,
    ): array {
        if ($entityIds === []) {
            return [];
        }

        $query = $this->query()
            ->where('module_code', $moduleCode)
            ->whereIn('entity_id', $entityIds)
            ->where('usage', $usage);
        if ($kind !== null) {
            $query = $query->where('kind', $kind);
        }
        $rows = $query
            ->orderBy('entity_id', 'ASC')
            ->orderBy('id', 'DESC')
            ->getArray();
        $files = [];
        foreach ($rows as $row) {
            $entityId = (int) $row['entity_id'];
            if (!isset($files[$entityId])) {
                $files[$entityId] = $this->metadata($row);
            }
        }

        return $files;
    }

    /**
     * Vrati soubory jednoho owner usage v canonical poradi
     *
     * @return list<FileMetadata>
     */
    public function listForEntity(
        string $moduleCode,
        int $entityId,
        string $usage,
        ?string $kind = null,
    ): array {
        return $this->metadataList(
            $this->forEntityUsage($moduleCode, $entityId, $usage, $kind)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->getArray(),
        );
    }

    /**
     * Vrati soubory vice usage jednoho vlastnika jednim dotazem
     *
     * @param list<string> $usages
     * @return list<FileMetadata>
     */
    public function listForEntityUsages(
        string $moduleCode,
        int $entityId,
        array $usages,
        ?string $kind = null,
    ): array {
        if ($usages === []) {
            return [];
        }

        $query = $this->query()
            ->where('module_code', $moduleCode)
            ->where('entity_id', $entityId)
            ->whereIn('usage', $usages);
        if ($kind !== null) {
            $query = $query->where('kind', $kind);
        }

        return $this->metadataList($query
            ->orderBy('usage', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->getArray());
    }

    /**
     * Vrati soubor jen pokud patri explicitnimu owner usage targetu
     */
    public function findForTarget(
        string $moduleCode,
        int $entityId,
        string $usage,
        int $fileId,
    ): ?FileMetadata {
        $row = $this->query()
            ->where('id', $fileId)
            ->where('module_code', $moduleCode)
            ->where('entity_id', $entityId)
            ->where('usage', $usage)
            ->first();

        return $row === null ? null : $this->metadata($row);
    }

    /**
     * Sestavi zaklad dotazu pro jedno owner usage
     */
    private function forEntityUsage(
        string $moduleCode,
        int $entityId,
        string $usage,
        ?string $kind,
    ): QueryBuilder {
        $query = $this->query()
            ->where('module_code', $moduleCode)
            ->where('entity_id', $entityId)
            ->where('usage', $usage);

        return $kind === null ? $query : $query->where('kind', $kind);
    }

    /**
     * Prevede databazovy radek na sdilena typovana metadata
     *
     * @param array<string,mixed> $row
     */
    private function metadata(array $row): FileMetadata
    {
        return new FileMetadata(
            id: (int) $row['id'],
            moduleCode: (string) $row['module_code'],
            entityId: (int) $row['entity_id'],
            usage: (string) $row['usage'],
            kind: (string) $row['kind'],
            mediaCategory: (string) $row['media_category'],
            assetId: $this->nullableString($row['asset_id'] ?? null),
            sourceVersion: $this->nullableString($row['source_version'] ?? null),
            storagePath: $this->nullableString($row['storage_path'] ?? null),
            originalFilename: (string) $row['original_filename'],
            displayName: $this->nullableString($row['display_name'] ?? null),
            caption: $this->nullableString($row['caption'] ?? null),
            extension: (string) $row['extension'],
            mimeType: (string) $row['mime_type'],
            fileSize: (int) $row['file_size'],
            width: $row['width'] === null ? null : (int) $row['width'],
            height: $row['height'] === null ? null : (int) $row['height'],
            sortOrder: (int) $row['sort_order'],
            createdAt: $this->nullableString($row['created_at'] ?? null),
            updatedAt: $this->nullableString($row['updated_at'] ?? null),
        );
    }

    /**
     * Prevede databazove radky na serazeny seznam metadat
     *
     * @param list<array<string,mixed>> $rows
     * @return list<FileMetadata>
     */
    private function metadataList(array $rows): array
    {
        return array_map($this->metadata(...), $rows);
    }

    /**
     * Zachova nullable textovou hodnotu databazoveho radku
     */
    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
