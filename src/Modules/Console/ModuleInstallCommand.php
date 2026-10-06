<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Console;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Framework\Cli\CommandInterface;

/**
 * Instaluje jeden objeveny optional modul pres sdileny lifecycle
 */
final class ModuleInstallCommand implements CommandInterface
{
    /**
     * Prijima lifecycle sluzbu modulu
     */
    public function __construct(private readonly ModuleLifecycleService $modules) {}

    /**
     * Vraci nemenny nazev prikazu pro instalaci modulu
     */
    public function name(): string
    {
        return 'modules:install';
    }

    /**
     * Popisuje instalaci optional modulu v CLI napovede
     */
    public function description(): string
    {
        return 'Installs one discovered optional module.';
    }

    /**
     * Instaluje modul zadany prvnim argumentem nebo vrati chybu pouziti
     */
    public function run(array $args): int
    {
        $code = $args[0] ?? null;
        if (!is_string($code) || $code === '') {
            fwrite(STDERR, "Usage: modules:install <module-code>\n");
            return 1;
        }
        $state = $this->modules->install($code, AuditActor::migration('modules-install'));
        echo sprintf("Installed %s (enabled=%s).\n", $state->code(), $state->enabled() ? '1' : '0');
        return 0;
    }
}
