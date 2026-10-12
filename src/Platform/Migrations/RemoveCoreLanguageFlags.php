<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Odstranuje zastaraly kod vlajky z katalogu systemovych jazyku
 */
final class RemoveCoreLanguageFlags implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20261012100000_remove_core_language_flags';
    }

    /**
     * Odstranuje nepouzivany sloupec flag_code z existujicich instalaci
     */
    public function up(Schema $schema): void
    {
        $schema->table('system_language', static function (TableBlueprint $table): void {
            $table->dropColumn('flag_code');
        });
    }
}
