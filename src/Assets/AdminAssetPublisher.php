<?php

declare(strict_types=1);

namespace Lemonade\Admin\Assets;

use FilesystemIterator;
use Lemonade\Framework\Core\Context\ApplicationContext;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Publikuje hotovou Admin distribuci do public rootu host aplikace
 */
final class AdminAssetPublisher
{
    /**
     * Nastavuje host public root a package distribuci urcenou k publikaci
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly AdminAssetConfiguration $configuration,
        private readonly ?string $distributionPath = null,
    ) {}

    /**
     * Zkopiruje overenou package distribuci a odstrani zastarale soubory cile
     */
    public function publish(): void
    {
        $source = $this->distributionPath ?? self::distributionPath();
        $this->assertValidDistribution($source);

        $destination = $this->context->publicPath($this->configuration->publicDirectory);
        $this->clearDirectory($destination);
        $this->copyDirectory($source, $destination);
    }

    /**
     * Vraci package-owned root hotove Admin distribuce
     */
    public static function distributionPath(): string
    {
        return dirname(__DIR__) . '/Resources/public';
    }

    /**
     * Overuje manifest a vsechny nim odkazovane soubory pred zmenou host ciloveho adresare
     */
    private function assertValidDistribution(string $source): void
    {
        $manifestPath = $source . '/.vite/manifest.json';
        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            throw new RuntimeException(sprintf('Admin asset distribution manifest is missing: %s.', $manifestPath));
        }

        $contents = file_get_contents($manifestPath);
        $manifest = $contents === false ? null : json_decode($contents, true);
        if (!is_array($manifest)) {
            throw new RuntimeException(sprintf('Admin asset distribution manifest is invalid: %s.', $manifestPath));
        }

        foreach ($manifest as $chunk) {
            if (!is_array($chunk)) {
                throw new RuntimeException('Admin asset distribution manifest contains an invalid chunk.');
            }

            foreach ([$chunk['file'] ?? null, ...(is_array($chunk['css'] ?? null) ? $chunk['css'] : [])] as $asset) {
                if (!is_string($asset) || !$this->isSafeAssetPath($asset) || !is_file($source . '/' . $asset)) {
                    throw new RuntimeException('Admin asset distribution manifest references a missing or unsafe asset.');
                }
            }
        }
    }

    /**
     * Rozhoduje, zda manifest odkazuje pouze do vlastni distribuce
     */
    private function isSafeAssetPath(string $path): bool
    {
        return $path !== '' && !str_starts_with($path, '/') && !str_contains($path, '..') && !str_contains($path, '\\');
    }

    /**
     * Vymaze pouze dosavadni Admin destination pred uplnou synchronizaci
     */
    private function clearDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }
    }

    /**
     * Zkopiruje vsechny package soubory pri zachovani jejich relativnich cest
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $target = $destination . '/' . $relativePath;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0775, true);
                }

                continue;
            }

            copy($item->getPathname(), $target);
        }
    }
}
