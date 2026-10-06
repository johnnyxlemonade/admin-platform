<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Migrations;

use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Zapisuje systemovy Media modul do katalogu bez nove persistence tabulky
 */
final class RegisterMediaModule implements MigrationInterface
{
    public function __construct(private readonly DatabaseDriverInterface $database) {}

    public static function identifier(): string
    {
        return '20261004140000_register_media_module';
    }

    /**
     * Zajisti enabled registraci builtin management modulu
     */
    public function up(Schema $schema): void
    {
        $this->database->query("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.media','Média',1,35)");
    }
}
