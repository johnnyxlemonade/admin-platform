<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Zapisuje systemovy modul roli do katalogu modulu
 */
final class RegisterRolesModule implements MigrationInterface
{
    /**
     * Nastavuje databazovy pristup pro registracni migraci
     */
    public function __construct(private readonly DatabaseDriverInterface $database) {}

    public static function identifier(): string
    {
        return '20260920101000_register_roles_module';
    }

    /**
     * Zajisti existenci enabled zaznamu systemoveho modulu roli
     */
    public function up(Schema $schema): void
    {
        $this->database->query("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.roles','Role a oprávnění',1,55)");
    }
}
