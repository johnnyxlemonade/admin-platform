<?php

declare(strict_types=1);

namespace Lemonade\Admin\Assets;

use DateTimeImmutable;
use JsonException;
use Lemonade\Framework\Core\Context\ApplicationContext;

/**
 * Cte manifest Admin assetu publikovanych do public rootu host aplikace
 */
final class AdminAssetManifest
{
    /**
     * Nastavuje host konfiguraci a kontext pro hledani publikovaneho manifestu
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly AdminAssetConfiguration $configuration,
    ) {}

    /**
     * Vraci CSS assety prirazene ke stabilnimu Admin entry
     *
     * @return list<string>
     */
    public function styles(string $entry): array
    {
        $chunk = $this->entry($entry);
        if ($chunk === null) {
            return [];
        }

        $styles = str_ends_with($chunk['file'], '.css') ? [$chunk['file']] : [];

        return array_values(array_filter(
            array_map(
                fn(string $path): ?string => $this->assetUrl($path),
                array_values(array_unique([...$styles, ...$chunk['css']])),
            ),
            static fn(?string $url): bool => $url !== null,
        ));
    }

    /**
     * Vraci JavaScript asset prirazeny ke stabilnimu Admin entry
     */
    public function script(string $entry): string
    {
        $chunk = $this->entry($entry);
        if ($chunk === null || !str_ends_with($chunk['file'], '.js')) {
            return '';
        }

        return $this->assetUrl($chunk['file']) ?? '';
    }

    /**
     * Overuje, zda publikovany manifest obsahuje CSS pro dany entry
     */
    public function hasStyles(string $entry): bool
    {
        return $this->styles($entry) !== [];
    }

    /**
     * Overuje, zda publikovany manifest obsahuje JavaScript pro dany entry
     */
    public function hasScript(string $entry): bool
    {
        return $this->script($entry) !== '';
    }

    /**
     * Vraci verejnou URL statickeho souboru z Admin distribuce
     */
    public function staticUrl(string $path, ?DateTimeImmutable $now = null): string
    {
        $url = $this->configuration->publicUrl($path);
        $version = ($now ?? new DateTimeImmutable())->format('o-\\WW');

        return $url . '?v=' . rawurlencode($version);
    }

    /**
     * Vraci absolutni cestu k manifestu publikovanemu hostem
     */
    public function manifestPath(): string
    {
        return $this->context->publicPath($this->configuration->publicDirectory . '/.vite/manifest.json');
    }

    /**
     * Nacita jeden validni manifestovy entry
     *
     * @return array{file:string,css:list<string>}|null
     */
    private function entry(string $entry): ?array
    {
        $manifest = $this->manifest();
        if ($manifest === null) {
            return null;
        }

        foreach ($manifest as $chunk) {
            if (!is_array($chunk) || ($chunk['isEntry'] ?? false) !== true || ($chunk['name'] ?? null) !== $entry) {
                continue;
            }

            $file = $chunk['file'] ?? null;
            $css = $chunk['css'] ?? [];
            if (!is_string($file) || $file === '' || !is_array($css) || array_filter($css, static fn(mixed $path): bool => !is_string($path) || $path === '') !== []) {
                return null;
            }

            return ['file' => $file, 'css' => array_values($css)];
        }

        return null;
    }

    /**
     * Nacita publikovany manifest bez expozice chyby do bezneho renderovani
     *
     * @return array<string, mixed>|null
     */
    private function manifest(): ?array
    {
        $path = $this->manifestPath();
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        try {
            $contents = file_get_contents($path);
            $manifest = $contents === false ? null : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            return is_array($manifest) ? $manifest : null;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Sklada verejnou URL pouze pro bezpecnou relativni cestu z manifestu
     */
    private function assetUrl(string $path): ?string
    {
        if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '\\')) {
            return null;
        }

        return $this->configuration->publicUrl($path);
    }
}
