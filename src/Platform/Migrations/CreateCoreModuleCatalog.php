<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari katalog modulu a jejich funkci
 */
final class CreateCoreModuleCatalog implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170100_create_core_module_catalog';
    }

    /**
     * Vytvari katalog modulu a jejich funkci
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

        $schema->create('system_module_feature', static function (TableBlueprint $table): void {
            $table->unsignedBigInteger('module_id')->comment('Modul, kteremu funkce patri');
            $table->string('feature', 50)->comment('Stabilni identifikator funkce modulu');
            $table->boolean('enabled')->default(1)->comment('Urcuje dostupnost funkce modulu');
            $table->string('provider', 50)->nullable()->comment('Poskytovatel nebo zdroj funkce');
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['module_id', 'feature']);
            $table->foreign('module_id', 'fk_feature_module')
                ->references('id')
                ->on('system_module')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / nastaveni funkci modulu');
        }, ifNotExists: true);
    }
}
