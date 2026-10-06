<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari katalog roli a opravneni administrace
 */
final class CreateCoreAuthorizationCatalog implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170500_create_core_authorization_catalog';
    }

    /**
     * Vytvari katalog roli a opravneni
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_role', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator role');
            $table->string('code', 100)->unique()->comment('Stabilni kod role');
            $table->string('name', 150)->unique('uq_system_role_name')->comment('Zobrazovany nazev role');
            $table->text('description')->nullable()->comment('Volitelny popis ucelu role');
            $table->boolean('is_system')->default(0)->comment('Systemova role se stabilnim kodem');
            $table->boolean('is_super_admin')->default(0)->comment('Urcuje roli s bypass opravneni');
            $table->timestamps();
            $table->softDeletes()->comment('Cas soft-delete role; NULL znamena existujici zaznam');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / role administrace');
        }, ifNotExists: true);

        $schema->create('system_permission', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator opravneni');
            $table->string('code', 150)->unique()->comment('Stabilni bezpecnostni kod opravneni');
            $table->string('module_code', 100)->comment('Modul, ktery opravneni vlastni');
            $table->string('name_key', 150)->comment('Lokalizacni klic nazvu opravneni');
            $table->timestamps();
            $table->softDeletes();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / katalog opravneni');
        }, ifNotExists: true);
    }
}
