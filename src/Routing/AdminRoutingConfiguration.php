<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use InvalidArgumentException;

/**
 * Urcuje hostem zvolenou zakladni cestu administrace
 */
final readonly class AdminRoutingConfiguration
{
    public string $basePath;

    /**
     * Normalizuje zakladni cestu bez trailing slash a URL casti
     */
    public function __construct(string $basePath)
    {
        $basePath = trim($basePath);
        if ($basePath === '' || $basePath === '/' || !str_starts_with($basePath, '/') || str_contains($basePath, '?') || str_contains($basePath, '#')) {
            throw new InvalidArgumentException('Admin base path must be a non-root absolute path without query or fragment.');
        }

        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Sklada admin cestu z relativniho segmentu routy
     */
    public function path(string $path = ''): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return $this->basePath;
        }

        return $this->basePath . '/' . ltrim($path, '/');
    }

    /**
     * Rozhoduje, zda cesta patri do admin namespace hosta
     */
    public function contains(string $path): bool
    {
        return $path === $this->basePath || str_starts_with($path, $this->basePath . '/');
    }
}
