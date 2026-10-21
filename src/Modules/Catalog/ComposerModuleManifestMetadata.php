<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use JsonException;
use RuntimeException;

/**
 * Cte deklarace modulu z Composer installed metadata
 */
final readonly class ComposerModuleManifestMetadata
{
    /**
     * Vrati manifest classy a zobrazitelna metadata jejich Composer balicku
     *
     * @return list<array{
     *     manifestClass: class-string,
     *     packageName: string,
     *     version: string,
     *     description: string,
     * }>
     */
    public static function modules(string $installedMetadataPath): array
    {
        if (!is_file($installedMetadataPath)) {
            throw new RuntimeException(sprintf(
                'Composer installed package metadata "%s" is missing. Run Composer install to complete the application installation.',
                $installedMetadataPath,
            ));
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

        return self::declaredModules($installedMetadata);
    }

    /**
     * Vybere moduly z Composer package metadata
     *
     * @param array<mixed> $installedMetadata
     * @return list<array{
     *     manifestClass: class-string,
     *     packageName: string,
     *     version: string,
     *     description: string,
     * }>
     */
    private static function declaredModules(array $installedMetadata): array
    {
        $packages = array_is_list($installedMetadata)
            ? $installedMetadata
            : ($installedMetadata['packages'] ?? null);
        if (!is_array($packages) || !array_is_list($packages)) {
            throw new RuntimeException('Composer installed package metadata must contain a package list.');
        }

        $declaredModules = [];
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

            $packageName = $package['name'] ?? null;
            $version = $package['pretty_version'] ?? $package['version'] ?? null;
            $description = $package['description'] ?? '';
            if (!is_string($packageName) || $packageName === '') {
                throw new RuntimeException('Composer package declaring Lemonade modules must have a non-empty name.');
            }
            if (!is_string($version) || $version === '') {
                throw new RuntimeException(sprintf('Composer package "%s" declaring Lemonade modules must have a non-empty version.', $packageName));
            }
            if (!is_string($description)) {
                throw new RuntimeException(sprintf('Composer package "%s" description must be a string.', $packageName));
            }

            foreach ($modules as $class) {
                if (!is_string($class) || $class === '') {
                    throw new RuntimeException('Composer package lemonade.modules metadata must contain non-empty manifest class names.');
                }

                /** @var class-string $class */
                $declaredModules[] = [
                    'manifestClass' => $class,
                    'packageName' => $packageName,
                    'version' => $version,
                    'description' => $description,
                ];
            }
        }

        usort(
            $declaredModules,
            static fn(array $left, array $right): int => $left['manifestClass'] <=> $right['manifestClass'],
        );

        return $declaredModules;
    }
}
