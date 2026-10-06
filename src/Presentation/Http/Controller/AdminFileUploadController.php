<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation\Http\Controller;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Presentation\AdminFileImageOriginalWriter;
use Lemonade\Admin\Presentation\AdminFileMutationService;
use Lemonade\Admin\Presentation\AdminFilePhysicalCleanupException;
use Lemonade\Admin\Presentation\AdminFileRenameModalDefinitionFactory;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\UploadedFile as PsrUploadedFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Obsluhuje shared upload a odstraneni image originalu pro autorizovane Admin editory
 */
final class AdminFileUploadController
{
    /**
     * Nastavuje editorovou authorization, image metadata, storage mutaci a JSON odpovedi
     */
    public function __construct(
        private readonly EditorDispatcher $editors,
        private readonly AdminFileMutationService $files,
        private readonly AdminFileImageOriginalWriter $originals,
        private readonly AdminFileUploadComponent $uploads,
        private readonly AdminFileUsageRegistry $usages,
        private readonly AdminFileModel $fileModel,
        private readonly AdminFileRenameModalDefinitionFactory $renameModal,
        private readonly ChunkUploadService $chunks,
        private readonly Filesystem $filesystem,
        private readonly Responses $responses,
        private readonly TranslatorInterface $translator,
        private readonly ViewRendererInterface $views,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Vytvori serverovou chunk session s autorizovanym immutable target contextem
     */
    public function start(string $module, string $usage, int $entity, ServerRequestInterface $request): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $definition = $this->usages->require($module, $usage);
            $data = new RequestData($request);
            $filename = trim((string) $data->input('filename', ''));
            $size = filter_var($data->input('size'), FILTER_VALIDATE_INT);
            if ($filename === '' || !is_int($size) || $size <= 0) {
                throw new \InvalidArgumentException('Upload filename and size are required.');
            }
            $session = $this->chunks->start(
                kind: $definition->kind(),
                profile: $definition->profile(),
                originalFilename: $filename,
                declaredSize: $size,
                context: [
                    'module_code' => $module,
                    'entity_id' => $entity,
                    'usage' => $usage,
                ],
            );
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json([
            'success' => true,
            'uploadId' => $session->uploadId(),
            'offset' => $session->currentOffset(),
            'chunkBytes' => $this->chunks->chunkBytes(),
        ]);
    }

    /**
     * Pripoji jeden sekvencni chunk po authorization proti serverovemu contextu
     */
    public function append(string $uploadId, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $session = $this->chunks->session($uploadId);
            if (($response = $this->authorizeSession($session->context)) !== null) {
                return $response;
            }
            $offset = filter_var((new RequestData($request))->header('Upload-Offset'), FILTER_VALIDATE_INT);
            if (!is_int($offset) || $offset < 0) {
                throw new \InvalidArgumentException('Upload offset is required.');
            }
            $updated = $this->chunks->append($uploadId, $offset, $request->getBody());
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json(['success' => true, 'offset' => $updated->currentOffset()]);
    }

    /**
     * Finalizuje chunk payload pres Framework profile a vraci canonical shared file state
     */
    public function complete(string $uploadId): ResponseInterface
    {
        try {
            $session = $this->chunks->session($uploadId);
            if (($response = $this->authorizeSession($session->context)) !== null) {
                return $response;
            }
            $uploaded = $this->chunks->complete($uploadId);
            $context = $this->targetContext($session->context);
            $definition = $this->usages->require($context['module'], $context['usage']);
            $fileId = $definition->kind() === 'image'
                ? $this->finalizeImage($context, $definition->multiple(), $uploaded, $session->originalFilename)
                : $this->files->saveGenericUpload(
                    module: $context['module'],
                    entityId: $context['entity'],
                    usage: $context['usage'],
                    originalFilename: $session->originalFilename,
                    uploaded: $uploaded,
                    multiple: $definition->multiple(),
                );
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json([
            'success' => true,
            'fileId' => $fileId,
            'files' => $this->uploads->collectionState($context['module'], $context['entity'], $context['usage']),
        ]);
    }

    /**
     * Zrusi jen session, jejiz serverovy context je pro aktualniho aktora editovatelny
     */
    public function abort(string $uploadId): ResponseInterface
    {
        try {
            $session = $this->chunks->session($uploadId);
            if (($response = $this->authorizeSession($session->context)) !== null) {
                return $response;
            }
            $this->chunks->abort($uploadId);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json(['success' => true]);
    }

    /**
     * Odstrani jednu file identity z explicitniho autorizovaneho shared targetu.
     */
    public function removeCollectionFile(string $module, string $usage, int $entity, int $file): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $this->usages->require($module, $usage);
            $this->files->removeTargetFile($module, $entity, $usage, $file);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json([
            'success' => true,
            'files' => $this->uploads->collectionState($module, $entity, $usage),
        ]);
    }

    /**
     * Ulozi jedno cele poradi shared sortable usage bez trustu v jednotlive file identity.
     */
    public function reorder(string $module, string $usage, int $entity, ServerRequestInterface $request): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $definition = $this->usages->require($module, $usage);
            if (!$definition->multiple() || !$definition->sortable()) {
                throw new \LogicException('This file usage is not sortable.');
            }
            $fileIds = (new RequestData($request))->input('fileIds', []);
            if (!is_array($fileIds)) {
                throw new \InvalidArgumentException('File order is required.');
            }
            $normalized = [];
            foreach ($fileIds as $fileId) {
                $id = filter_var($fileId, FILTER_VALIDATE_INT);
                if (!is_int($id) || $id <= 0) {
                    throw new \InvalidArgumentException('File order contains an invalid identifier.');
                }
                $normalized[] = $id;
            }
            $this->files->reorder($module, $entity, $usage, $normalized);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json([
            'success' => true,
            'files' => $this->uploads->collectionState($module, $entity, $usage),
        ]);
    }

    /**
     * Renderuje canonical modal pro prejmenovani file identity overene proti targetu
     */
    public function renameModal(string $module, string $usage, int $entity, int $file): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $this->usages->require($module, $usage);
            $targetFile = $this->requireTargetFile($module, $entity, $usage, $file);
        } catch (\Throwable $exception) {
            return $this->notFound();
        }

        return $this->responses->json([
            'success' => true,
            'modalHtml' => $this->views->content('admin::components.file-rename-modal', [
                'adminEditor' => $this->renameModal->modal($module, $usage, $entity, $file),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: ['display_name' => $targetFile['display_name'] ?? $targetFile['original_filename']],
                    mode: 'edit',
                ),
            ]),
        ]);
    }

    /**
     * Ulozi display name jednoho souboru po stejne authorization a target kontrole jako remove
     */
    public function rename(string $module, string $usage, int $entity, int $file, ServerRequestInterface $request): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $this->usages->require($module, $usage);
            $this->requireTargetFile($module, $entity, $usage, $file);
            $payload = (new RequestData($request))->jsonPayload()['payload'] ?? null;
            $displayName = is_array($payload) && is_string($payload['display_name'] ?? null)
                ? trim($payload['display_name'])
                : '';
            if ($displayName === '') {
                return $this->renameValidationError('admin.file_upload.display_name_required');
            }
            if (mb_strlen($displayName, 'UTF-8') > 255) {
                return $this->renameValidationError('admin.file_upload.display_name_max_length');
            }
            $renamed = $this->files->renameTargetFile($module, $entity, $usage, $file, $displayName);
        } catch (\Throwable $exception) {
            return $this->notFound();
        }

        return $this->responses->json([
            'success' => true,
            'messageKey' => 'admin.file_upload.renamed',
            'file' => $renamed,
        ]);
    }

    /**
     * Pouzije standardni editorovou access a existence kontrolu pro shared image slot
     */
    private function authorize(string $module, int $entity): ?ResponseInterface
    {
        try {
            $this->editors->updateData($module, $entity);
        } catch (EditorEntityNotFoundException) {
            return $this->notFound();
        } catch (EditorCapabilityException $exception) {
            $status = $exception->statusCode();

            return $this->responses->json([
                'success' => false,
                'error' => ['code' => $status === HttpStatusCode::FORBIDDEN ? AdminErrorCode::PERMISSION_DENIED->value : AdminErrorCode::NOT_FOUND->value],
            ], $status->value);
        }

        return null;
    }

    /**
     * Vraci file jen pokud patri explicitnimu modulu, entite a usage targetu
     *
     * @return array<string,mixed>
     */
    private function requireTargetFile(string $module, int $entity, string $usage, int $file): array
    {
        $targetFile = $this->fileModel->findForTarget($module, $entity, $usage, $file);
        if ($targetFile === null) {
            throw new \RuntimeException('File not found.');
        }

        return $targetFile;
    }

    /**
     * Vraci field validation ve standardnim action JSON tvaru
     */
    private function renameValidationError(string $translationKey): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'errors' => ['display_name' => $this->translator->get($translationKey)],
        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Overi application-owned target metadata nactena vyhradne z chunk session
     *
     * @param array<string,mixed> $context
     */
    private function authorizeSession(array $context): ?ResponseInterface
    {
        $target = $this->targetContext($context);

        return $this->authorize($target['module'], $target['entity']);
    }

    /**
     * Normalizuje opaque session context na jediny povoleny Admin target tvar
     *
     * @param array<string,mixed> $context
     * @return array{module:string,entity:int,usage:string}
     */
    private function targetContext(array $context): array
    {
        $module = $context['module_code'] ?? null;
        $entity = $context['entity_id'] ?? null;
        $usage = $context['usage'] ?? null;
        if (!is_string($module) || $module === '' || !is_int($entity) || $entity <= 0 || !is_string($usage) || $usage === '') {
            throw new \RuntimeException('Chunk upload target context is invalid.');
        }

        return ['module' => $module, 'entity' => $entity, 'usage' => $usage];
    }

    /**
     * Mapuje infrastructure a validation failures na jednotny JSON upload contract
     */
    private function uploadError(\Throwable $exception): ResponseInterface
    {
        $this->logger->error('Admin file upload mutation failed.', [
            'exception' => $exception,
            'exception_class' => $exception::class,
        ]);

        return $this->responses->json([
            'success' => false,
            'error' => ['code' => AdminErrorCode::VALIDATION_FAILED->value],
            'errors' => ['file' => $this->translator->get('admin.file_upload.failed')],
        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Prevede Framework image profile output na canonical Admin image asset a kompenzuje neuspesny bind
     *
     * @param array{module:string,entity:int,usage:string} $target
     */
    private function finalizeImage(array $target, bool $multiple, UploadedFile $uploaded, string $originalFilename): int
    {
        $resource = fopen($uploaded->storedPath(), 'rb');
        if ($resource === false) {
            throw new \RuntimeException('Finalized image cannot be opened.');
        }
        $assetId = $multiple ? bin2hex(random_bytes(16)) : $this->files->imageAssetId($target['module'], $target['entity'], $target['usage']);
        $definition = $this->usages->require($target['module'], $target['usage']);
        $written = null;
        $source = new PsrUploadedFile(
            $resource,
            $uploaded->sizeBytes(),
            UPLOAD_ERR_OK,
            $originalFilename,
            $uploaded->mimeType(),
        );
        try {
            $written = $this->originals->write($assetId, $source, $definition->profile());
        } finally {
            // Nyholm UploadedFile owns the resource through its PSR stream. Closing
            // that stream is idempotent; fclose($resource) would close it twice.
            $source->getStream()->close();
            $this->filesystem->delete($uploaded->storedPath());
        }

        try {
            return $multiple
                ? $this->files->saveAdditionalImageUpload($target['module'], $target['entity'], $target['usage'], $assetId, $written['sourceVersion'], $written['metadata'])
                : $this->files->saveImageUpload($target['module'], $target['entity'], $target['usage'], $assetId, $written['sourceVersion'], $written['metadata']);
        } catch (AdminFilePhysicalCleanupException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            try {
                $this->files->discardImageOriginal($assetId, $written['sourceVersion'], $written['metadata']);
            } catch (\Throwable $cleanupException) {
                throw new \RuntimeException('Image persistence failed and canonical cleanup failed.', previous: $cleanupException);
            }

            throw $exception;
        }
    }

    /**
     * Vraci jednotnou 404 odpoved bez informace o storage nebo image metadata
     */
    private function notFound(): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'error' => ['code' => AdminErrorCode::NOT_FOUND->value],
        ], HttpStatusCode::NOT_FOUND->value);
    }
}
