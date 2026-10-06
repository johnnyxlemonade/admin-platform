<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari lokalizovane prefixy verejnych rout modulu
 */
final class CreateCoreModuleRoutePrefixes implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170200_create_core_module_route_prefixes';
    }

    /**
     * Vytvari prefixy navazane na moduly a jazyky
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_module_route_prefix', static function (TableBlueprint $table): void {
            $table->unsignedBigInteger('module_id')->comment('Modul, kteremu prefix patri');
            $table->string('locale', 35)->comment('Jazyk prefixu routy');
            $table->string('prefix', 120)->comment('Lokalizovany prefix verejne routy modulu');
            $table->timestamps();
            $table->softDeletes()->comment('Cas soft-delete zaznamu; NULL znamena existujici zaznam');
            $table->primary(['module_id', 'locale']);
            $table->unique(['locale', 'prefix'], 'uq_module_route_prefix_locale_prefix');
            $table->foreign('module_id', 'fk_prefix_module')
                ->references('id')
                ->on('system_module')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->foreign('locale', 'fk_prefix_locale')
                ->references('code')
                ->on('system_language')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / lokalizovane prefixy modulovych rout');
        }, ifNotExists: true);
    }
}
