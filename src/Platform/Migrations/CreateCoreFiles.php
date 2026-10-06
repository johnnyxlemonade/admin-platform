<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari canonical metadata originalu obrazu prirazenych zaznamum Admin modulu
 */
final class CreateCoreFiles implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20261003110000_create_core_files';
    }

    /**
     * Vytvari tabulku image identity, usage a metadata bez binarniho obsahu
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_file', static function (TableBlueprint $table): void {
            $table->id()->comment('Stabilni identifikator souboru pouzity pro storage a URL');
            $table->string('module_code', 100)->comment('Modul vlastnici file usage');
            $table->unsignedBigInteger('entity_id')->comment('Identifikator vlastneneho zaznamu');
            $table->string('usage', 64)->comment('Semanticky slot image v zaznamu');
            $table->string('kind', 20)->comment('Zakladni druh souboru image, document nebo other');
            $table->string('asset_id', 128)->nullable()->comment('Framework image asset identity pro image soubor');
            $table->string('source_version', 128)->nullable()->comment('Framework immutable source version pro image soubor');
            $table->string('storage_path', 500)->nullable()->comment('Interni storage key generic souboru relativni k upload rootu');
            $table->string('original_filename', 255)->comment('Puvodni nazev nahraneho souboru');
            $table->string('display_name', 255)->nullable()->comment('Volitelny presentation nazev souboru');
            $table->string('caption', 500)->nullable()->comment('Volitelny kratky popisek souboru');
            $table->string('extension', 10)->comment('Pripona Frameworkem overeneho originalu');
            $table->string('mime_type', 100)->comment('MIME Frameworkem overeneho originalu');
            $table->unsignedBigInteger('file_size')->comment('Velikost ulozeneho originalu v bytech');
            $table->unsignedInteger('width')->nullable()->comment('Sirka obrazku v pixelech nebo NULL pro prilohu');
            $table->unsignedInteger('height')->nullable()->comment('Vyska obrazku v pixelech nebo NULL pro prilohu');
            $table->unsignedInteger('sort_order')->default(0)->comment('Poradi souboru v opakovatelne owner usage kolekci');
            $table->timestamps();
            $table->index(['module_code', 'entity_id', 'usage'], 'idx_system_file_owner_usage');
            $table->index(['module_code', 'entity_id', 'usage', 'sort_order'], 'idx_system_file_owner_usage_sort');
            $table->index(['module_code', 'created_at'], 'idx_system_file_module_created');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / metadata souboru Admin modulu');
        }, ifNotExists: true);
    }
}
