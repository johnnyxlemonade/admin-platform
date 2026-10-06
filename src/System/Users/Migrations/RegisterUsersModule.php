<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Registruje systemovy Users modul v lifecycle tabulce
 */
final class RegisterUsersModule implements MigrationInterface
{
    /**
     * Nastavi databazovy driver pro registraci modulu
     */
    public function __construct(private readonly DatabaseDriverInterface $database) {}

    public static function identifier(): string
    {
        return '20260914200000_register_users_module';
    }

    /**
     * Zajisti aktivni systemovy zaznam Users capability
     */
    public function up(Schema $schema): void
    {
        $this->database->query("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.users','Uživatelé',1,50)");
    }
}
