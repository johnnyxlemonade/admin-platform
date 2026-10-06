<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Console;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Catalog\ModuleManifestDiscovery;
use Lemonade\Framework\Cli\CommandInterface;

/**
 * Vytvari diagnosticky snapshot Composer manifestu modulu
 */
final class ModuleDiscoverCommand implements CommandInterface
{
    /**
     * Prijima discovery sluzbu a cilovy katalog modulu
     */
    public function __construct(
        private readonly ModuleManifestDiscovery $discovery,
        private readonly ModuleCatalog $catalog,
    ) {}

    /**
     * Vraci nemenny nazev prikazu pro vytvoreni katalogu
     */
    public function name(): string
    {
        return 'modules:discover';
    }

    /**
     * Popisuje vytvoreni diagnostickeho snapshotu v CLI napovede
     */
    public function description(): string
    {
        return 'Writes a diagnostic snapshot of Composer module manifests.';
    }

    /**
     * Zapise snapshot z Composer manifestu bez vlivu na bezny runtime
     *
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        unset($args);
        $this->discovery->writeCatalog($this->catalog);
        fwrite(STDOUT, sprintf("Module catalog written to %s.\n", $this->catalog->cachePath()));

        return 0;
    }
}
