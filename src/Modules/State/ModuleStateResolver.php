<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\State;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Framework\Cache\CacheManager;

/**
 * Zjistuje stav modulu pro dalsi sluzby
 */
final class ModuleStateResolver
{
    private const CACHE_KEY = 'admin.module-enabled-map.v1';
    /** @var array<string, ModuleManifestInterface> */
    private array $manifests = [];
    /** @var array<string, bool> */
    private array $databaseStates = [];
    private bool $manifestsLoaded = false;
    private bool $databaseStatesLoaded = false;

    public function __construct(
        private readonly ModuleCatalog $catalog,
        private readonly ModuleModel $modules,
        private readonly ?CacheManager $cache = null,
    ) {}

    /** @return list<ModuleManifestInterface> */
    public function manifests(): array
    {
        $this->loadManifests();

        return array_values($this->manifests);
    }

    public function state(string $code): ModuleState
    {
        $this->loadManifests();
        $this->loadDatabaseStates();
        $manifest = $this->manifests[$code] ?? null;
        if ($manifest === null) {
            return new ModuleState($code, false, false, false, null, isset($this->databaseStates[$code]));
        }
        if ($manifest->kind() === ModuleKind::System) {
            return new ModuleState($code, true, true, true, ModuleKind::System, false);
        }

        $installed = isset($this->databaseStates[$code]);

        return new ModuleState($code, true, $installed, $installed && $this->databaseStates[$code], ModuleKind::Optional, false);
    }

    public function manifest(string $code): ?ModuleManifestInterface
    {
        $this->loadManifests();

        return $this->manifests[$code] ?? null;
    }

    /** @return list<ModuleState> */
    public function all(): array
    {
        $this->loadManifests();
        $this->loadDatabaseStates();
        $codes = array_unique([...array_keys($this->manifests), ...array_keys($this->databaseStates)]);
        sort($codes);

        return array_map(fn(string $code): ModuleState => $this->state($code), $codes);
    }

    public function refresh(): void
    {
        $this->databaseStates = [];
        $this->databaseStatesLoaded = false;
    }

    /**
     * Zrusi persistentni snapshot po uspesne zmene lifecycle modulu
     */
    public function forgetCachedDatabaseStates(): void
    {
        $this->refresh();
        $this->cache?->forget(self::CACHE_KEY);
    }

    private function loadManifests(): void
    {
        if ($this->manifestsLoaded) {
            return;
        }
        $this->manifests = [];
        foreach ($this->catalog->load() as $manifest) {
            $this->manifests[$manifest->code()] = $manifest;
        }
        $this->manifestsLoaded = true;
    }

    private function loadDatabaseStates(): void
    {
        if ($this->databaseStatesLoaded) {
            return;
        }
        if ($this->cache === null) {
            $this->databaseStates = $this->modules->enabledMap();
            $this->databaseStatesLoaded = true;

            return;
        }
        /** @var array<string, bool> $states */
        $states = $this->cache->rememberForever(self::CACHE_KEY, fn(): array => $this->modules->enabledMap());
        $this->databaseStates = $states;
        $this->databaseStatesLoaded = true;
    }
}
