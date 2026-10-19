<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Console;

use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Framework\Cli\CommandInterface;

/**
 * Spousti cekajici migrace nainstalovanych optional modulu
 */
final readonly class ModuleMigrateCommand implements CommandInterface
{
    /**
     * Prijima lifecycle sluzbu modulu
     */
    public function __construct(private readonly ModuleLifecycleService $modules) {}

    /**
     * Vraci nemenny nazev prikazu pro migrace modulu
     */
    public function name(): string
    {
        return 'modules:migrate';
    }

    /**
     * Popisuje spusteni cekajicich migraci v CLI napovede
     */
    public function description(): string
    {
        return 'Runs pending migrations for installed optional modules.';
    }

    /**
     * Spusti cekajici migrace a vypise aplikovane identifikatory
     */
    public function run(array $args): int
    {
        unset($args);
        foreach ($this->modules->migrateInstalled() as $code => $identifiers) {
            foreach ($identifiers as $identifier) {
                echo sprintf("Migrated %s: %s\n", $code, $identifier);
            }
        }
        return 0;
    }
}
