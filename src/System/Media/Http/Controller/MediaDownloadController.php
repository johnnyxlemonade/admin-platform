<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Http\Controller;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Presentation\AdminFileOriginalPathResolver;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Http\Response\Responses;
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
        private readonly AdminFileOriginalPathResolver $originals,
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

        $path = $this->originals->pathFor($metadata);
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
