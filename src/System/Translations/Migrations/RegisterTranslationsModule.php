<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Zapisuje systemovy modul prekladu do lifecycle katalogu
 */
final class RegisterTranslationsModule implements MigrationInterface
{
    /**
     * Nastavuje databazovy pristup pro registracni migraci
     */
    public function __construct(private readonly DatabaseDriverInterface $database) {}

    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20261003130000_register_translations_module';
    }

    /**
     * Zajisti enabled zaznam povinneho systemoveho modulu
     */
    public function up(Schema $schema): void
    {
        unset($schema);
        $this->database->query("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.translations','Překlady',1,50)");
    }
}
