<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Http\Controller;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Presentation\AdminFileImageAssetResolver;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Image\ImageIdentifier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Odesila autorizovanemu administratorovi puvodni soubor z katalogu medii
 */
final class MediaDownloadController
{
    /**
     * Nastavuje authorization, shared file metadata, storage resolvery a download odpovedi
     */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AdminResponseFactory $adminResponses,
        private readonly AdminFileModel $files,
        private readonly AdminFileImageAssetResolver $assets,
        private readonly ImageVariantPathResolver $paths,
        private readonly ApplicationContext $context,
        private readonly Responses $responses,
    ) {}

    /**
     * Overi pristup a odesle existujici image nebo generic original jako attachment
     */
    public function download(int $file, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorization->hasPermission('system.media.view')) {
            return $this->adminResponses->authorizationDenied($request);
        }

        $metadata = $this->files->findForManagement($file);
        if ($metadata === null) {
            return $this->adminResponses->notFound($request);
        }

        $path = $this->downloadPath($metadata, $file);
        if ($path === null || !is_file($path)) {
            return $this->adminResponses->notFound($request);
        }

        return $this->responses->download(
            filePath: $path,
            downloadName: $this->filename($metadata),
            contentType: (string) $metadata['mime_type'],
        );
    }

    /**
     * Vrati canonical original path pro image nebo generic file metadata
     *
     * @param array<string,mixed> $metadata
     */
    private function downloadPath(array $metadata, int $file): ?string
    {
        if ($metadata['kind'] === 'file') {
            $storagePath = $metadata['storage_path'] ?? null;

            return is_string($storagePath) && $storagePath !== '' && !str_starts_with($storagePath, '/')
                ? $this->context->uploadPath($storagePath)
                : null;
        }
        if ($metadata['kind'] !== 'image') {
            return null;
        }

        $asset = $this->assets->resolve(
            module: (string) $metadata['module_code'],
            identifier: ImageIdentifier::fromString((string) $file),
        );
        if ($asset === null) {
            return null;
        }

        return $this->paths->originalPath($asset);
    }

    /**
     * Vrati bezpecny uzivatelsky nazev attachmentu z ulozenych metadata
     *
     * @param array<string,mixed> $metadata
     */
    private function filename(array $metadata): string
    {
        $displayName = trim((string) ($metadata['display_name'] ?? ''));
        $filename = $displayName === '' ? (string) $metadata['original_filename'] : $displayName;

        return str_replace(["\r", "\n"], '', basename($filename));
    }
}
