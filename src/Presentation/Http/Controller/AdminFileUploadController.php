<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation\Http\Controller;

use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Presentation\AdminFileImageOriginalWriter;
use Lemonade\Admin\Presentation\AdminFileMutationService;
use Lemonade\Admin\Presentation\AdminFilePhysicalCleanupException;
use Lemonade\Admin\Presentation\AdminFileRenameModalDefinitionFactory;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Admin\Presentation\AdminFileUsageDefinition;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Chunk\ChunkUploadService;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\Exception\ChunkUploadQuotaExceededException;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Obsluhuje shared upload a odstraneni image originalu pro autorizovane Admin editory
 */
final readonly class AdminFileUploadController
{
    /**
     * Nastavuje editorovou authorization, image metadata, storage mutaci a JSON odpovedi
     */
    public function __construct(
        private EditorDispatcher $editors,
        private AdminFileMutationService $files,
        private AdminFileImageOriginalWriter $originals,
        private AdminFileUploadComponent $uploads,
        private AdminFileUsageRegistry $usages,
        private AdminFileModel $fileModel,
        private AdminFileRenameModalDefinitionFactory $renameModal,
        private ChunkUploadService $chunks,
        private UploadConfig $uploadConfig,
        private Filesystem $filesystem,
        private Responses $responses,
        private TranslatorInterface $translator,
        private ViewRendererInterface $views,
        private LoggerInterface $logger,
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
            $pipeline = $this->uploadPipeline($definition, $filename);
            if (($validation = $this->startValidationError($pipeline['kind'], $pipeline['profile'], $filename, $size)) !== null) {
                return $validation;
            }
            $session = $this->chunks->start(
                kind: $pipeline['kind'],
                profile: $pipeline['profile'],
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
     * Vrati aktualni aktivni soubory jednoho autorizovaneho owner targetu
     */
    public function collection(string $module, string $usage, int $entity): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $this->usages->require($module, $usage);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        return $this->responses->json([
            'success' => true,
            'files' => $this->uploads->collectionState($module, $entity, $usage),
        ]);
    }

    /**
     * Pripoji jeden sekvencni chunk po authorization proti serverovemu contextu
     */
    public function append(string $uploadId, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $session = $this->chunks->session($uploadId);
        } catch (UploadValidationException $exception) {
            return $this->sessionUnavailable($exception);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        try {
            if (($response = $this->authorizeSession($session->context)) !== null) {
                return $response;
            }
            $offset = filter_var((new RequestData($request))->header('Upload-Offset'), FILTER_VALIDATE_INT);
            if (!is_int($offset) || $offset < 0) {
                throw new \InvalidArgumentException('Upload offset is required.');
            }
            if ($offset !== $session->currentOffset) {
                return $this->uploadFailure('offset_conflict', HttpStatusCode::CONFLICT);
            }
            try {
                $updated = $this->chunks->append($uploadId, $offset, $request->getBody());
            } catch (UploadValidationException $exception) {
                try {
                    $latest = $this->chunks->session($uploadId);
                } catch (UploadValidationException $sessionException) {
                    return $this->sessionUnavailable($sessionException);
                }

                if ($latest->currentOffset !== $offset) {
                    return $this->uploadFailure('offset_conflict', HttpStatusCode::CONFLICT);
                }

                return $this->uploadError($exception);
            }
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
        } catch (UploadValidationException $exception) {
            return $this->sessionUnavailable($exception);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        try {
            if (($response = $this->authorizeSession($session->context)) !== null) {
                return $response;
            }
            $context = $this->targetContext($session->context);
            $definition = $this->usages->require($context['module'], $context['usage']);
            $pipeline = $this->uploadPipeline($definition, $session->originalFilename);
            if ($session->kind !== $pipeline['kind'] || $session->profile !== $pipeline['profile']) {
                throw new \RuntimeException('Chunk upload pipeline no longer matches its registered target.');
            }
            $uploaded = $this->chunks->complete($uploadId);
            if ($session->kind === 'image' && $uploaded instanceof UploadedImage) {
                $fileId = $this->finalizeImage($context, $definition->multiple(), $uploaded, $session->originalFilename);
            } elseif ($session->kind === 'file' && !($uploaded instanceof UploadedImage)) {
                $fileId = $this->files->saveGenericUpload(
                    module: $context['module'],
                    entityId: $context['entity'],
                    usage: $context['usage'],
                    originalFilename: $session->originalFilename,
                    uploaded: $uploaded,
                    multiple: $definition->multiple(),
                );
            } else {
                $this->filesystem->delete($uploaded->storedPath());

                throw new \RuntimeException('Chunk upload result does not match its server-owned pipeline.');
            }
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
        } catch (UploadValidationException $exception) {
            return $this->sessionUnavailable($exception);
        } catch (\Throwable $exception) {
            return $this->uploadError($exception);
        }

        try {
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
     * Renderuje canonical modal pro upravu presentation metadat file identity overene proti targetu
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
            'modalHtml' => $this->views->content('admin::components.admin-editor-modal', [
                'modalId' => 'admin-file-presentation',
                'titleKey' => 'admin.file_upload.edit_title',
                'adminEditor' => $this->renameModal->modal($module, $usage, $entity, $file),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: [
                        'display_name' => $targetFile['display_name'] ?? $targetFile['original_filename'],
                        'caption' => $targetFile['caption'] ?? '',
                    ],
                    mode: 'edit',
                ),
            ]),
        ]);
    }

    /**
     * Ulozi presentation metadata jednoho souboru po stejne authorization a target kontrole jako remove
     */
    public function rename(string $module, string $usage, int $entity, int $file, ServerRequestInterface $request): ResponseInterface
    {
        if (($response = $this->authorize($module, $entity)) !== null) {
            return $response;
        }

        try {
            $this->usages->require($module, $usage);
            $targetFile = $this->requireTargetFile($module, $entity, $usage, $file);
            $payload = (new RequestData($request))->jsonPayload()['payload'] ?? null;
            $displayName = is_array($payload) && is_string($payload['display_name'] ?? null)
                ? trim($payload['display_name'])
                : '';
            if ($displayName === '') {
                return $this->presentationValidationError('display_name', 'admin.file_upload.display_name_required');
            }
            if (mb_strlen($displayName, 'UTF-8') > 255) {
                return $this->presentationValidationError('display_name', 'admin.file_upload.display_name_max_length');
            }
            $captionInput = is_array($payload) && array_key_exists('caption', $payload)
                ? $payload['caption']
                : $targetFile['caption'] ?? null;
            if (!is_string($captionInput) && $captionInput !== null) {
                return $this->presentationValidationError('caption', 'admin.file_upload.caption_max_length');
            }
            $caption = is_string($captionInput) ? trim($captionInput) : null;
            $caption = $caption === '' ? null : $caption;
            if ($caption !== null && mb_strlen($caption, 'UTF-8') > 500) {
                return $this->presentationValidationError('caption', 'admin.file_upload.caption_max_length');
            }
            $updatedFile = $this->files->updateTargetFilePresentation($module, $entity, $usage, $file, $displayName, $caption);
        } catch (\Throwable $exception) {
            return $this->notFound();
        }

        return $this->responses->json([
            'success' => true,
            'messageKey' => 'admin.file_upload.presentation_updated',
            'file' => $updatedFile,
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
    private function presentationValidationError(string $field, string $translationKey): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'errors' => [$field => $this->translator->get($translationKey)],
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
        if ($exception instanceof ChunkUploadQuotaExceededException) {
            return $this->uploadFailure('quota_exceeded', HttpStatusCode::TOO_MANY_REQUESTS);
        }
        if ($exception instanceof UploadValidationException || $exception instanceof \InvalidArgumentException || $exception instanceof \OutOfBoundsException) {
            return $this->uploadFailure('invalid_file', HttpStatusCode::UNPROCESSABLE_ENTITY);
        }

        $this->logger->error('Admin file upload mutation failed.', [
            'exception' => $exception,
            'exception_class' => $exception::class,
        ]);

        return $this->responses->json([
            'success' => false,
            'error' => [
                'code' => AdminErrorCode::UPLOAD_FAILED->value,
                'reason' => 'infrastructure_failure',
            ],
            'errors' => ['file' => $this->translator->get('admin.file_upload.failed')],
        ], HttpStatusCode::INTERNAL_SERVER_ERROR->value);
    }

    /**
     * Vraci pred zahajenim chunk session pouze chyby, ktere lze rozhodnout z authoritativniho upload profilu
     */
    private function startValidationError(
        string $kind,
        string $profileName,
        string $filename,
        int $size,
    ): ?ResponseInterface {
        $profile = $kind === 'image'
            ? $this->uploadConfig->images[$profileName] ?? null
            : $this->uploadConfig->files[$profileName] ?? null;
        if ($profile === null) {
            throw new \OutOfBoundsException('Upload profile is not configured.');
        }
        if ($size > $profile->maxBytes()) {
            return $this->uploadValidationError('file_too_large');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = array_map(strtolower(...), $profile->allowedExtensions());
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            return $this->uploadValidationError('extension_not_allowed');
        }

        return null;
    }

    /**
     * Vraci strojove citelny duvod upload validation bez duplikace upload profilu na klientovi
     */
    private function uploadValidationError(string $reason): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'error' => [
                'code' => AdminErrorCode::VALIDATION_FAILED->value,
                'reason' => $reason,
            ],
            'errors' => ['file' => $this->translator->get('admin.file_upload.failed')],
        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Vraci strukturovanou upload chybu se zachovanym klientskym payload kontraktem
     */
    private function uploadFailure(string $reason, HttpStatusCode $status): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'error' => [
                'code' => AdminErrorCode::VALIDATION_FAILED->value,
                'reason' => $reason,
            ],
            'errors' => ['file' => $this->translator->get('admin.file_upload.failed')],
        ], $status->value);
    }

    /**
     * Skryje rozdil mezi expirovanou a neznamou session za retry-safe konflikt
     */
    private function sessionUnavailable(UploadValidationException $exception): ResponseInterface
    {
        $this->logger->notice('Admin chunk upload session is no longer available.', [
            'exception_class' => $exception::class,
        ]);

        return $this->uploadFailure('session_expired', HttpStatusCode::CONFLICT);
    }

    /**
     * Zvoli serverovy kind a profil pred vznikem chunk session podle povolene image pripony
     *
     * @return array{kind:string,profile:string}
     */
    private function uploadPipeline(AdminFileUsageDefinition $definition, string $filename): array
    {
        $imageProfileName = $definition->imageProfile();

        return $definition->uploadPipeline(
            $filename,
            $imageProfileName === null ? null : ($this->uploadConfig->images[$imageProfileName] ?? null),
        );
    }

    /**
     * Prevede Framework image profile output na canonical Admin image asset a kompenzuje neuspesny bind
     *
     * @param array{module:string,entity:int,usage:string} $target
     */
    private function finalizeImage(array $target, bool $multiple, UploadedImage $uploaded, string $originalFilename): int
    {
        $assetId = $multiple ? bin2hex(random_bytes(16)) : $this->files->imageAssetId($target['module'], $target['entity'], $target['usage']);
        try {
            $written = $this->originals->write($assetId, $uploaded, $originalFilename);
        } finally {
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
