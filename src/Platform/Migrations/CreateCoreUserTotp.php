<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari sifrovane TOTP credentialy lokalnich uzivatelu
 */
final class CreateCoreUserTotp implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20261009180000_create_core_user_totp';
    }

    /**
     * Vytvari jeden TOTP zaznam pro kazdeho lokalniho uzivatele
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_user_totp', static function (TableBlueprint $table): void {
            $table->unsignedBigInteger('user_id')->comment('Lokalni uzivatel TOTP credentialu');
            $table->string('secret_ciphertext', 255)->nullable()->comment('Aktivni sifrovany TOTP secret');
            $table->string('pending_secret_ciphertext', 255)->nullable()->comment('Sifrovany secret cekajici na potvrzeni');
            $table->datetime('enrollment_started_at')->nullable()->comment('Cas zahajeni rozpracovane registrace');
            $table->datetime('enrollment_expires_at')->nullable()->comment('Cas expirace rozpracovane registrace');
            $table->unsignedBigInteger('last_consumed_time_step')->nullable()->comment('Posledni spotrebovany TOTP casovy krok');
            $table->datetime('enabled_at')->nullable()->comment('Cas uspesne aktivace credentialu');
            $table->datetime('last_revoked_at')->nullable()->comment('Cas posledniho zruseni credentialu');
            $table->timestamps();
            $table->primary('user_id', 'pk_system_user_totp');
            $table->foreign('user_id', 'fk_system_user_totp_user')
                ->references('id')
                ->on('system_user')
                ->cascadeOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade Admin / TOTP credentialy uzivatelu');
        }, ifNotExists: true);
    }
}
