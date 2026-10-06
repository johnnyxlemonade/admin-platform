<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari lifecycle zaznam systemoveho management modulu oznameni
 */
final class RegisterNotificationsModule implements MigrationInterface
{
    public function __construct(private readonly DatabaseDriverInterface $database) {}

    /**
     * Identifikuje registracni migraci v globalnim migration ledgeru
     */
    public static function identifier(): string
    {
        return '20260918180000_register_notifications_module';
    }

    /**
     * Vklada aktivni systemovy management modul do lifecycle tabulky
     */
    public function up(Schema $schema): void
    {
        unset($schema);

        $this->database->query(
            "INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.notifications','Oznámení',1,58)",
        );
    }
}
