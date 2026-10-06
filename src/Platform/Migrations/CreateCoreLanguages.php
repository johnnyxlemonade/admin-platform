<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari katalog systemovych jazyku
 */
final class CreateCoreLanguages implements MigrationInterface
{
    /**
     * Prijima pripojeni pro canonical seed jazyka
     */
    public function __construct(
        private readonly DatabaseDriverInterface $database,
    ) {}

    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170000_create_core_languages';
    }

    /**
     * Vytvari katalog jazyku a seed vychozi cestiny
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_language', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator jazyka');
            $table->string('code', 35)->unique()->comment('Stabilni kod jazyka');
            $table->string('name', 100)->comment('Nazev jazyka v administraci');
            $table->string('flag_code', 2)->comment('ISO 3166-1 alpha-2 kod vlajky jazyka');
            $table->boolean('enabled')->comment('Urcuje dostupnost jazyka');
            $table->boolean('is_default')->comment('Urcuje vychozi jazyk systemu');
            $table->integer('sort_order')->comment('Poradi jazyka v seznamech');
            $table->timestamps();
            $table->softDeletes()->comment('Cas soft-delete zaznamu; NULL znamena existujici zaznam');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / katalog jazyku');
        }, ifNotExists: true);

        $now = date('Y-m-d H:i:s');
        $this->database->query(
            'INSERT IGNORE INTO system_language (code,name,flag_code,enabled,is_default,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',
            ['cs', 'Čeština', 'CZ', 1, 1, 0, $now, $now],
        );
    }
}
