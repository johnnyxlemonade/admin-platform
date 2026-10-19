<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Http\Controller;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Presentation\AdminFileMutationService;
use Lemonade\Admin\Presentation\AdminFileRenameModalDefinitionFactory;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Upravuje presentation metadata libovolne existujici file identity z Media katalogu
 */
final readonly class MediaFilePresentationController
{
    /**
     * Nastavuje Media authorization, shared file mutation, modal definition a JSON odpovedi
     */
    public function __construct(
        private AuthorizationService $authorization,
        private AdminResponseFactory $adminResponses,
        private AdminFileModel $files,
        private AdminFileMutationService $mutations,
        private AdminFileRenameModalDefinitionFactory $modal,
        private Responses $responses,
        private TranslatorInterface $translator,
        private ViewRendererInterface $views,
    ) {}

    /**
     * Nacte shared modal pro existujici file pouze s Media edit opravnenim
     */
    public function modal(int $file, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorization->hasPermission('system.media.edit')) {
            return $this->adminResponses->authorizationDenied($request);
        }

        $targetFile = $this->files->findForManagement($file);
        if ($targetFile === null) {
            return $this->adminResponses->notFound($request);
        }

        return $this->responses->json([
            'success' => true,
            'modalHtml' => $this->views->content('admin::components.admin-editor-modal', [
                'modalId' => 'admin-file-presentation',
                'titleKey' => 'admin.file_upload.edit_title',
                'adminEditor' => $this->modal->mediaModal($file),
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
     * Ulozi presentation metadata pod Media opravnenim a audit pripise skutecnemu ownerovi
     */
    public function save(int $file, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorization->hasPermission('system.media.edit')) {
            return $this->adminResponses->authorizationDenied($request);
        }

        $targetFile = $this->files->findForManagement($file);
        if ($targetFile === null) {
            return $this->adminResponses->notFound($request);
        }

        $payload = (new RequestData($request))->jsonPayload()['payload'] ?? null;
        $displayName = is_array($payload) && is_string($payload['display_name'] ?? null)
            ? trim($payload['display_name'])
            : '';
        if ($displayName === '') {
            return $this->validationError('display_name', 'admin.file_upload.display_name_required');
        }
        if (mb_strlen($displayName, 'UTF-8') > 255) {
            return $this->validationError('display_name', 'admin.file_upload.display_name_max_length');
        }
        $captionInput = is_array($payload) && array_key_exists('caption', $payload)
            ? $payload['caption']
            : $targetFile['caption'] ?? null;
        if (!is_string($captionInput) && $captionInput !== null) {
            return $this->validationError('caption', 'admin.file_upload.caption_max_length');
        }
        $caption = is_string($captionInput) ? trim($captionInput) : null;
        $caption = $caption === '' ? null : $caption;
        if ($caption !== null && mb_strlen($caption, 'UTF-8') > 500) {
            return $this->validationError('caption', 'admin.file_upload.caption_max_length');
        }

        try {
            $target = $this->target($targetFile);
            $updatedFile = $this->mutations->updateTargetFilePresentation(
                module: $target['module'],
                entityId: $target['entity'],
                usage: $target['usage'],
                fileId: $file,
                displayName: $displayName,
                caption: $caption,
            );
        } catch (\Throwable) {
            return $this->adminResponses->notFound($request);
        }

        return $this->responses->json([
            'success' => true,
            'messageKey' => 'admin.file_upload.presentation_updated',
            'file' => $updatedFile,
        ]);
    }

    /**
     * Vraci field validation ve standardnim modal action JSON tvaru
     */
    private function validationError(string $field, string $translationKey): ResponseInterface
    {
        return $this->responses->json([
            'success' => false,
            'errors' => [$field => $this->translator->get($translationKey)],
        ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Cte owner metadata vyhradne z nalezeneho canonical file zaznamu
     *
     * @param array<string,mixed> $file
     * @return array{module:string,entity:int,usage:string}
     */
    private function target(array $file): array
    {
        $module = $file['module_code'] ?? null;
        $entity = $file['entity_id'] ?? null;
        $usage = $file['usage'] ?? null;
        if (!is_string($module) || $module === '' || !is_int($entity) || $entity <= 0 || !is_string($usage) || $usage === '') {
            throw new \RuntimeException('Media file target metadata is invalid.');
        }

        return ['module' => $module, 'entity' => $entity, 'usage' => $usage];
    }
}
