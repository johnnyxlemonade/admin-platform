<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization\Console;

use Lemonade\Admin\Authorization\PermissionSyncService;
use Lemonade\Framework\Cli\CommandInterface;

/**
 * Synchronizuje katalog opravneni deklarovany Admin moduly
 */
final class PermissionsSyncCommand implements CommandInterface
{
    /**
     * Prijima sluzbu pro synchronizaci katalogu opravneni
     */
    public function __construct(private readonly PermissionSyncService $permissions) {}

    /**
     * Vraci nemenny nazev synchronizacniho prikazu
     */
    public function name(): string
    {
        return 'permissions:sync';
    }

    /**
     * Popisuje synchronizaci katalogu v CLI napovede
     */
    public function description(): string
    {
        return 'Synchronizes module permission catalogs.';
    }

    /**
     * Synchronizuje katalog a vypise vysledek jednotlivych zmen
     */
    public function run(array $args): int
    {
        unset($args);
        $report = $this->permissions->sync();
        fwrite(STDOUT, sprintf("inserted=%d updated=%d unchanged=%d stale=%d\n", $report->inserted(), $report->updated(), $report->unchanged(), count($report->stale())));
        return 0;
    }
}
