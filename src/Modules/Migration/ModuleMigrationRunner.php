<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Migration;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Database\Migration\MigrationRunner;
use Lemonade\Framework\Database\Migration\MigrationStateRepository;
use Lemonade\Framework\Database\Schema\Schema;
use LogicException;

/**
 * Spousti migrace instalovanych modulu
 */
final class ModuleMigrationRunner
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ModuleCatalog $catalog,
        private readonly MigrationRegistry $globalRegistry,
        private readonly MigrationStateRepository $ledger,
        private readonly Schema $schema,
    ) {}

    public function migrate(ModuleMigrationManifestInterface $manifest): ModuleMigrationResult
    {
        $registry = new MigrationRegistry($this->container);
        $global = array_fill_keys($this->globalRegistry->identifiers(), true);
        foreach ($this->catalog->load() as $declared) {
            if (!$declared instanceof ModuleMigrationManifestInterface || $declared->code() === $manifest->code()) {
                continue;
            }
            foreach ($declared->migrations() as $migration) {
                $identifier = $migration::identifier();
                if (isset($global[$identifier])) {
                    throw new LogicException(sprintf('Module migration identifier "%s" is not globally unique.', $identifier));
                }
                $global[$identifier] = true;
            }
        }
        foreach ($manifest->migrations() as $migration) {
            $identifier = $migration::identifier();
            if (isset($global[$identifier])) {
                throw new LogicException(sprintf('Module migration identifier "%s" is already globally registered.', $identifier));
            }
            $registry->register($migration);
            $global[$identifier] = true;
        }

        return new ModuleMigrationResult($manifest->code(), (new MigrationRunner($registry, $this->ledger, $this->schema))->migrate());
    }
}
