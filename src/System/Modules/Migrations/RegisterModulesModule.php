<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Migrations;

use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari lifecycle zaznam systemoveho modulu pro management modulu
 */
final class RegisterModulesModule implements MigrationInterface
{
    public function __construct(private readonly Database $database) {}

    /**
     * Identifikuje registracni migraci v globalnim migration ledgeru
     */
    public static function identifier(): string
    {
        return '20260921100000_register_modules_module';
    }

    /**
     * Vklada systemovy management modul bez zmeny existujiciho lifecycle zaznamu
     */
    public function up(Schema $schema): void
    {
        unset($schema);
        $this->database->statement("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.modules','Modules',1,65)");
    }
}
