<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari katalog modulu
 */
final readonly class CreateCoreModuleCatalog implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170100_create_core_module_catalog';
    }

    /**
     * Vytvari katalog modulu
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_module', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator modulu');
            $table->string('code', 100)->unique()->comment('Stabilni kod modulu');
            $table->string('name', 150)->comment('Technicky nazev modulu');
            $table->boolean('enabled')->default(1)->comment('Urcuje dostupnost modulu za behu');
            $table->integer('sort_order')->default(0)->comment('Poradi modulu v administraci');
            $table->timestamps();
            $table->softDeletes();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / katalog modulu');
        }, ifNotExists: true);
    }
}
