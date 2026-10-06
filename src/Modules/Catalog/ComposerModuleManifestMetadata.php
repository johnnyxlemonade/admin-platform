<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use JsonException;
use RuntimeException;

/**
 * Cte deklarace manifestu samostatne instalovanych Composer modulu
 */
final class ComposerModuleManifestMetadata
{
    /**
     * Vrati manifest classy deklarovane Composer balicky
     *
     * @return list<class-string>
     */
    public static function manifestClasses(string $installedMetadataPath): array
    {
        if (!is_file($installedMetadataPath)) {
            return [];
        }

        $contents = file_get_contents($installedMetadataPath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Composer installed package metadata "%s" cannot be read.', $installedMetadataPath));
        }

        try {
            $installedMetadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Composer installed package metadata "%s" is invalid.', $installedMetadataPath),
                previous: $exception,
            );
        }

        if (!is_array($installedMetadata)) {
            throw new RuntimeException(sprintf('Composer installed package metadata "%s" must contain packages.', $installedMetadataPath));
        }

        return self::declaredManifestClasses($installedMetadata);
    }

    /**
     * Vybere manifest classy z Composer package metadata
     *
     * @param array<mixed> $installedMetadata
     * @return list<class-string>
     */
    private static function declaredManifestClasses(array $installedMetadata): array
    {
        $packages = array_is_list($installedMetadata)
            ? $installedMetadata
            : ($installedMetadata['packages'] ?? null);
        if (!is_array($packages) || !array_is_list($packages)) {
            throw new RuntimeException('Composer installed package metadata must contain a package list.');
        }

        $classes = [];
        foreach ($packages as $package) {
            if (!is_array($package)) {
                throw new RuntimeException('Composer installed package metadata must contain package objects.');
            }

            $extra = $package['extra'] ?? null;
            if (!is_array($extra) || !array_key_exists('lemonade', $extra)) {
                continue;
            }

            $lemonade = $extra['lemonade'];
            if (!is_array($lemonade)) {
                throw new RuntimeException('Composer package lemonade metadata must be an object.');
            }
            if (!array_key_exists('modules', $lemonade)) {
                continue;
            }

            $modules = $lemonade['modules'];
            if (!is_array($modules) || !array_is_list($modules)) {
                throw new RuntimeException('Composer package lemonade.modules metadata must be a list of manifest classes.');
            }

            foreach ($modules as $class) {
                if (!is_string($class) || $class === '') {
                    throw new RuntimeException('Composer package lemonade.modules metadata must contain non-empty manifest class names.');
                }

                /** @var class-string $class */
                $classes[] = $class;
            }
        }

        sort($classes, SORT_STRING);

        return $classes;
    }
}
