<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Image\ImageOriginalMetadata;

/**
 * Provadi auditovane ukladani a neobnovitelne odstraneni shared system_file zaznamu
 */
final class AdminFileMutationService
{
    /**
     * Nastavuje shared file metadata, auditni transakci a Framework storage contract
     */
    public function __construct(
        private readonly AdminFileModel $files,
        private readonly TransactionalEventProcessor $events,
        private readonly LocalActorGuard $actors,
        private readonly ImageVariantPathResolver $paths,
        private readonly Filesystem $filesystem,
        private readonly ApplicationContext $context,
        private readonly Database $database,
    ) {}

    /**
     * Vraci stabilni asset identitu pro zapis nove immutable image source version
     */
    public function imageAssetId(string $module, int $entityId, string $usage): string
    {
        $this->actors->requireLocalUser();
        $existing = $this->files->findActiveImageForEntity($module, $entityId, $usage);
        if (is_string($existing['asset_id'] ?? null) && $existing['asset_id'] !== '') {
            return $existing['asset_id'];
        }

        return bin2hex(random_bytes(16));
    }

    /**
     * Ulozi image metadata a audit po uspesnem fyzickem zapisu originalu
     */
    public function saveImageUpload(
        string $module,
        int $entityId,
        string $usage,
        string $assetId,
        string $sourceVersion,
        ImageOriginalMetadata $metadata,
    ): int {
        $existing = $this->files->findActiveImageForEntity($module, $entityId, $usage);
        $actor = $this->actors->requireLocalUser();
        $replacement = $existing !== null;
        $oldSourceVersion = $existing === null ? null : $this->sourceVersion($existing);
        $operation = $replacement ? 'files.replace' : 'files.upload';
        $eventCode = $replacement ? 'system.files.replaced' : 'system.files.uploaded';

        return $this->events->execute(
            new AuditOperation($module, $operation, AuditActor::user($actor->id())),
            function (TransactionalEventCollector $events) use ($module, $entityId, $usage, $assetId, $sourceVersion, $metadata, $replacement, $existing, $oldSourceVersion, $eventCode): int {
                $fileId = $replacement
                    ? (int) $existing['id']
                    : $this->files->createIdentity($module, $entityId, $usage, 'image');
                $this->files->updateImageSource($fileId, $assetId, $sourceVersion, $metadata);
                if ($replacement && $existing !== null && $oldSourceVersion !== $sourceVersion) {
                    $this->afterCommitDelete($existing);
                }
                $events->record(new DomainEvent(
                    $eventCode,
                    $module,
                    'system_file',
                    (string) $fileId,
                    $this->payload(
                        fileId: $fileId,
                        module: $module,
                        entityId: $entityId,
                        usage: $usage,
                        kind: 'image',
                        metadata: $metadata,
                        sourceVersion: $sourceVersion,
                        oldSourceVersion: $oldSourceVersion,
                    ),
                ));

                return $fileId;
            },
        );
    }

    /**
     * Persistuje jeden finalizovany generic upload pod sdilenou owner usage identitou
     */
    public function saveGenericUpload(
        string $module,
        int $entityId,
        string $usage,
        string $originalFilename,
        UploadedFile $uploaded,
        bool $multiple,
    ): int {
        $actor = $this->actors->requireLocalUser();
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $existing = $multiple ? null : $this->files->findForEntity($module, $entityId, $usage);

        try {
            return $this->events->execute(
                new AuditOperation($module, 'files.upload', AuditActor::user($actor->id())),
                function (TransactionalEventCollector $events) use ($module, $entityId, $usage, $originalFilename, $extension, $uploaded, $existing): int {
                    if ($existing !== null) {
                        if ($existing['kind'] !== 'file') {
                            throw new \LogicException('Single file usage has an incompatible existing file kind.');
                        }
                        $fileId = (int) $existing['id'];
                        $this->files->updateFile($fileId, $originalFilename, $extension, $uploaded->mimeType(), $uploaded->sizeBytes(), $this->storageKey($uploaded));
                        $this->afterCommitDelete($existing);
                    } else {
                        $fileId = $this->files->createFile(
                            module: $module,
                            entityId: $entityId,
                            usage: $usage,
                            originalFilename: $originalFilename,
                            extension: $extension,
                            mimeType: $uploaded->mimeType(),
                            fileSize: $uploaded->sizeBytes(),
                            storagePath: $this->storageKey($uploaded),
                        );
                    }
                    $events->record(new DomainEvent(
                        $existing === null ? 'system.files.uploaded' : 'system.files.replaced',
                        $module,
                        'system_file',
                        (string) $fileId,
                        [
                            'file_id' => $fileId,
                            'module_code' => $module,
                            'entity_id' => $entityId,
                            'usage' => $usage,
                            'kind' => 'file',
                            'original_filename' => $originalFilename,
                            'mime_type' => $uploaded->mimeType(),
                            'file_size' => $uploaded->sizeBytes(),
                        ],
                    ));

                    return $fileId;
                },
            );
        } catch (AdminFilePhysicalCleanupException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            try {
                $this->filesystem->delete($uploaded->storedPath());
            } catch (\Throwable $cleanupException) {
                throw new \RuntimeException('File persistence failed and canonical cleanup failed.', previous: $cleanupException);
            }

            throw $exception;
        }
    }

    /**
     * Persistuje novou image identity pro multiple usage po canonical image finalizaci
     */
    public function saveAdditionalImageUpload(
        string $module,
        int $entityId,
        string $usage,
        string $assetId,
        string $sourceVersion,
        ImageOriginalMetadata $metadata,
    ): int {
        $actor = $this->actors->requireLocalUser();

        return $this->events->execute(
            new AuditOperation($module, 'files.upload', AuditActor::user($actor->id())),
            function (TransactionalEventCollector $events) use ($module, $entityId, $usage, $assetId, $sourceVersion, $metadata): int {
                $fileId = $this->files->createIdentity($module, $entityId, $usage, 'image');
                $this->files->updateImageSource($fileId, $assetId, $sourceVersion, $metadata);
                $events->record(new DomainEvent(
                    'system.files.uploaded',
                    $module,
                    'system_file',
                    (string) $fileId,
                    $this->payload($fileId, $module, $entityId, $usage, 'image', $metadata, $sourceVersion, null),
                ));

                return $fileId;
            },
        );
    }

    /**
     * Odstrani canonical image original vytvoreny pred neuspesnym persistence bindem
     */
    public function discardImageOriginal(string $assetId, string $sourceVersion, ImageOriginalMetadata $metadata): void
    {
        $original = $this->paths->originalReference(
            $assetId,
            $sourceVersion,
            ImageOriginalStorage::Storage,
            $metadata->format(),
            new ImageDimensions($metadata->width(), $metadata->height()),
        );
        $this->filesystem->delete($this->paths->originalPath(new ImageAsset($assetId, $original)));
    }

    /**
     * Odstrani existujici soubor centralnim managementem bez soft-delete nebo restore
     */
    public function removeForManagement(int $fileId): void
    {
        $file = $this->files->findForManagement($fileId);
        if ($file === null) {
            throw new \RuntimeException('File not found.');
        }

        $actor = $this->actors->requireLocalUser();
        $this->removeOne(
            $file,
            new AuditOperation('system.media', 'files.remove', AuditActor::user($actor->id())),
        );
    }

    /**
     * Odstrani jeden soubor jen pokud patri autorizovanemu shared targetu.
     */
    public function removeTargetFile(string $module, int $entityId, string $usage, int $fileId): void
    {
        $file = $this->files->findForTarget($module, $entityId, $usage, $fileId);
        if ($file === null) {
            throw new \RuntimeException('File not found.');
        }

        $actor = $this->actors->requireLocalUser();
        $this->removeOne($file, new AuditOperation($module, 'files.remove', AuditActor::user($actor->id())));
    }

    /**
     * Ulozi presentation metadata souboru, ktery patri autorizovanemu targetu
     *
     * @return array<string,mixed>
     */
    public function updateTargetFilePresentation(string $module, int $entityId, string $usage, int $fileId, string $displayName, ?string $caption): array
    {
        $file = $this->files->findForTarget($module, $entityId, $usage, $fileId);
        if ($file === null) {
            throw new \RuntimeException('File not found.');
        }
        if (($file['display_name'] ?? null) === $displayName && ($file['caption'] ?? null) === $caption) {
            return $file;
        }

        $actor = $this->actors->requireLocalUser();
        $this->events->execute(
            new AuditOperation($module, 'files.rename', AuditActor::user($actor->id())),
            function (TransactionalEventCollector $events) use ($module, $entityId, $usage, $fileId, $displayName, $caption): void {
                $this->files->updatePresentation($fileId, $displayName, $caption);
                $events->record(new DomainEvent('system.files.renamed', $module, 'system_file', (string) $fileId, [
                    'file_id' => $fileId,
                    'module_code' => $module,
                    'entity_id' => $entityId,
                    'usage' => $usage,
                    'display_name' => $displayName,
                    'caption' => $caption,
                ]));
            },
        );

        $updatedFile = $this->files->findForTarget($module, $entityId, $usage, $fileId);
        if ($updatedFile === null) {
            throw new \RuntimeException('Updated file was not found.');
        }

        return $updatedFile;
    }

    /**
     * Ulozi pouze uplne a jednoznacne poradi existujici shared collection.
     *
     * @param list<int> $fileIds
     */
    public function reorder(string $module, int $entityId, string $usage, array $fileIds): void
    {
        $current = $this->files->listForEntity($module, $entityId, $usage);
        $currentIds = array_map(static fn(array $file): int => (int) $file['id'], $current);
        sort($currentIds);
        $requestedIds = $fileIds;
        sort($requestedIds);
        if ($requestedIds !== $currentIds || count($fileIds) !== count(array_unique($fileIds))) {
            throw new \InvalidArgumentException('The complete target file collection is required for reorder.');
        }

        $actor = $this->actors->requireLocalUser();
        $this->events->execute(
            new AuditOperation($module, 'files.reorder', AuditActor::user($actor->id())),
            function (TransactionalEventCollector $events) use ($module, $entityId, $usage, $fileIds): void {
                $this->files->reorder($module, $entityId, $usage, $fileIds);
                $events->record(new DomainEvent('system.files.reordered', $module, 'system_file', (string) $entityId, [
                    'module_code' => $module,
                    'entity_id' => $entityId,
                    'usage' => $usage,
                    'file_ids' => $fileIds,
                ]));
            },
        );
    }

    /**
     * Odstrani existujici vyber v jedne DB auditni transakci a preskoci chybejici ID
     *
     * @param list<int> $fileIds
     * @return int Pocet skutecne odstranenych souboru
     */
    public function removeManyForManagement(array $fileIds): int
    {
        $files = $this->files->findManyForManagement($fileIds);
        if ($files === []) {
            return 0;
        }

        $actor = $this->actors->requireLocalUser();

        return $this->events->execute(
            new AuditOperation('system.media', 'files.remove', AuditActor::user($actor->id())),
            function (TransactionalEventCollector $events) use ($fileIds, $files): int {
                $changed = 0;
                foreach ($fileIds as $fileId) {
                    $file = $files[$fileId] ?? null;
                    if ($file === null) {
                        continue;
                    }

                    if (!$this->files->removeForManagement($fileId)) {
                        throw new \RuntimeException('File delete failed.');
                    }
                    $this->afterCommitDelete($file);
                    ++$changed;
                    $events->record($this->removedEvent($file, 'system.media'));
                }

                return $changed;
            },
        );
    }

    /**
     * Vytvari allowlistovany audit payload bez storage cesty nebo obsahu souboru
     *
     * @return array<string,int|string|null>
     */
    private function payload(
        int $fileId,
        string $module,
        int $entityId,
        string $usage,
        string $kind,
        ImageOriginalMetadata $metadata,
        string $sourceVersion,
        ?string $oldSourceVersion,
    ): array {
        $payload = [
            'file_id' => $fileId,
            'module_code' => $module,
            'entity_id' => $entityId,
            'usage' => $usage,
            'kind' => $kind,
            'original_filename' => $metadata->originalFilename(),
            'mime_type' => $metadata->format()->mimeType(),
            'file_size' => $metadata->size(),
            'source_version' => $sourceVersion,
        ];
        if ($oldSourceVersion !== null) {
            $payload['old_source_version'] = $oldSourceVersion;
        }

        return $payload;
    }

    /**
     * Vraci drive ulozenou source version jen pokud je soucasti aktivni identity
     *
     * @param array<string,mixed> $file
     */
    private function sourceVersion(array $file): ?string
    {
        return is_string($file['source_version'] ?? null) && $file['source_version'] !== ''
            ? $file['source_version']
            : null;
    }

    /**
     * Smaze canonical original az po committed odstraneni metadata; variants cache nehleda ani nemaze
     *
     * @param array<string,mixed> $file
     */
    private function deleteOriginal(array $file): void
    {
        if ($file['kind'] === 'file') {
            $storagePath = $file['storage_path'] ?? null;
            if (!is_string($storagePath) || $storagePath === '' || str_starts_with($storagePath, '/')) {
                throw new \RuntimeException('Generic file storage reference is invalid.');
            }
            $this->filesystem->delete($this->context->uploadPath($storagePath));

            return;
        }
        if ($file['kind'] !== 'image') {
            return;
        }

        $assetId = $file['asset_id'] ?? null;
        $sourceVersion = $file['source_version'] ?? null;
        if (!is_string($assetId) || $assetId === '' || !is_string($sourceVersion) || $sourceVersion === '' || $file['width'] === null || $file['height'] === null) {
            throw new \RuntimeException('Image file metadata is incomplete.');
        }

        try {
            $format = ImageFormat::fromMimeType((string) $file['mime_type']);
            $dimensions = new ImageDimensions((int) $file['width'], (int) $file['height']);
            $original = $this->paths->originalReference(
                $assetId,
                $sourceVersion,
                ImageOriginalStorage::Storage,
                $format,
                $dimensions,
            );
            $this->filesystem->delete($this->paths->originalPath(new ImageAsset($assetId, $original)));
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException('Image file metadata is invalid.', previous: $exception);
        }
    }

    /**
     * Odstrani metadata a az po commitu provede neobnovitelne physical cleanup
     *
     * @param array<string,mixed> $file
     */
    private function removeOne(array $file, AuditOperation $operation): void
    {
        $this->events->execute(
            $operation,
            function (TransactionalEventCollector $events) use ($file, $operation): void {
                $fileId = (int) $file['id'];
                if (!$this->files->removeForManagement($fileId)) {
                    throw new \RuntimeException('File delete failed.');
                }
                $this->afterCommitDelete($file);
                $events->record($this->removedEvent($file, $operation->moduleCode()));
            },
        );
    }

    /**
     * Zajisti, ze selhani post-commit cleanupu nelze zamenit za neuspesny DB bind.
     *
     * @param array<string,mixed> $file
     */
    private function afterCommitDelete(array $file): void
    {
        $this->database->afterCommit(function () use ($file): void {
            try {
                $this->deleteOriginal($file);
            } catch (\Throwable $exception) {
                throw new AdminFilePhysicalCleanupException('Committed file removal cleanup failed.', previous: $exception);
            }
        });
    }

    /**
     * Prevadi Framework relative public reference na storage key relativni k upload rootu.
     */
    private function storageKey(UploadedFile $uploaded): string
    {
        $relativePath = trim(str_replace('\\', '/', $uploaded->storedRelativePath()), '/');
        $prefix = trim($this->context->uploadRelativePath(), '/') . '/';
        if (!str_starts_with($relativePath, $prefix)) {
            throw new \RuntimeException('Finalized file storage reference is outside the upload root.');
        }

        return substr($relativePath, strlen($prefix));
    }

    /**
     * Zachyti metadata pred DB delete pro canonical shared file audit udalost
     *
     * @param array<string,mixed> $file
     */
    private function removedEvent(array $file, string $eventModule): DomainEvent
    {
        $fileId = (int) $file['id'];

        return new DomainEvent(
            'system.files.removed',
            $eventModule,
            'system_file',
            (string) $fileId,
            [
                'file_id' => $fileId,
                'module_code' => $file['module_code'],
                'entity_id' => $file['entity_id'],
                'usage' => $file['usage'],
                'kind' => $file['kind'],
                'original_filename' => $file['original_filename'],
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size'],
                'source_version' => $this->sourceVersion($file),
                'removed' => true,
            ],
        );
    }
}
