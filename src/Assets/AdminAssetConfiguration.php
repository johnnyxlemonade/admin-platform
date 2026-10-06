<?php

declare(strict_types=1);

namespace Lemonade\Admin\Assets;

use InvalidArgumentException;

/**
 * Urcuje hostem zvolenou verejnou URL a cil publikovanych Admin assetu
 */
final readonly class AdminAssetConfiguration
{
    public string $publicBaseUrl;

    public string $publicDirectory;

    /**
     * Normalizuje verejnou URL a relativni cil pod host public rootem
     */
    public function __construct(
        string $publicBaseUrl,
        string $publicDirectory,
    ) {
        if (
            !str_starts_with($publicBaseUrl, '/')
            || str_contains($publicBaseUrl, '?')
            || str_contains($publicBaseUrl, '#')
        ) {
            throw new InvalidArgumentException('Admin asset base URL must be an absolute path without query or fragment.');
        }

        $directory = trim($publicDirectory, '/');
        if ($directory === '' || str_contains($directory, '..') || str_contains($directory, '\\')) {
            throw new InvalidArgumentException('Admin asset public directory must be a safe non-empty relative path.');
        }

        $this->publicBaseUrl = rtrim($publicBaseUrl, '/') . '/';
        $this->publicDirectory = $directory;
    }

    /**
     * Sklada verejnou URL pro relativni soubor z Admin distribuce
     */
    public function publicUrl(string $path): string
    {
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new InvalidArgumentException('Admin asset path must be a safe non-empty relative path.');
        }

        return $this->publicBaseUrl . $path;
    }
}
