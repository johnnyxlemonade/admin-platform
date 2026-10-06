<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari canonical lokalni uzivatele administrace
 */
final class CreateCoreUsers implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170300_create_core_users';
    }

    /**
     * Vytvari tabulku lokalnich uzivatelu
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_user', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator uzivatele');
            $table->string('username', 100)->unique()->comment('Jedinecny prihlasovaci identifikator uzivatele');
            $table->string('first_name', 100)->nullable()->comment('Krestni jmeno uzivatele');
            $table->string('last_name', 100)->nullable()->comment('Prijmeni uzivatele');
            $table->string('email', 254)->nullable()->unique('uq_system_user_email')->comment('Jedinecna e-mailova adresa uzivatele');
            $table->string('phone', 35)->nullable()->comment('Telefonni kontakt uzivatele');
            $table->string('password_hash', 255)->nullable()->comment('Hash lokalniho hesla uzivatele');
            $table->boolean('active')->default(1)->comment('Urcuje moznost prihlaseni uzivatele');
            $table->unsignedInteger('version')->default(1)->comment('Verze zaznamu pro optimistic locking');
            $table->timestamps();
            $table->datetime('last_login_at')->nullable()->comment('Cas posledniho uspesneho prihlaseni');
            $table->softDeletes()->comment('Cas soft-delete uzivatele; NULL znamena existujici zaznam');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / uzivatele administrace');
        }, ifNotExists: true);
    }
}
