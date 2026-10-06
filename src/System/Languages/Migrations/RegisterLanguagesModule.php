<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari lifecycle zaznam povinneho systemoveho modulu jazyku
 */
final class RegisterLanguagesModule implements MigrationInterface
{
    public function __construct(private readonly DatabaseDriverInterface $database) {}

    /**
     * Identifikuje migraci v globalnim migration ledgeru
     */
    public static function identifier(): string
    {
        return '20260922110000_register_languages_module';
    }

    /**
     * Zapisuje aktivni modul jazyku do lifecycle tabulky
     */
    public function up(Schema $schema): void
    {
        $this->database->query("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.languages','Jazyky',1,45)");
    }
}
