<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Http\Controller;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Presentation\AdminFileImageAssetResolver;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Image\ImageIdentifier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Odesila autorizovanemu administratorovi puvodni obrazek z katalogu medii
 */
final class MediaDownloadController
{
    /**
     * Nastavuje authorization, shared file metadata, original resolver a download odpovedi
     */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AdminResponseFactory $adminResponses,
        private readonly AdminFileModel $files,
        private readonly AdminFileImageAssetResolver $assets,
        private readonly ImageVariantPathResolver $paths,
        private readonly Responses $responses,
    ) {}

    /**
     * Overi pristup a odesle existujici canonical original jako attachment
     */
    public function download(int $file, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorization->hasPermission('system.media.view')) {
            return $this->adminResponses->authorizationDenied($request);
        }

        $metadata = $this->files->findForManagement($file);
        if ($metadata === null || $metadata['kind'] !== 'image') {
            return $this->adminResponses->notFound($request);
        }

        $asset = $this->assets->resolve(
            module: (string) $metadata['module_code'],
            identifier: ImageIdentifier::fromString((string) $file),
        );
        if ($asset === null) {
            return $this->adminResponses->notFound($request);
        }

        $path = $this->paths->originalPath($asset);
        if (!is_file($path)) {
            return $this->adminResponses->notFound($request);
        }

        return $this->responses->download(
            filePath: $path,
            downloadName: $this->filename($metadata),
            contentType: (string) $metadata['mime_type'],
        );
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
