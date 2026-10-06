<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari vazby externich identit na lokalni uzivatele
 */
final class CreateCoreUserIdentities implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170400_create_core_user_identities';
    }

    /**
     * Vytvari tabulku canonical externich identit
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_user_identity', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator identity');
            $table->unsignedBigInteger('user_id')->comment('Lokalni uzivatel identity');
            $table->string('provider_key', 100)->comment('Technicky klic OIDC poskytovatele');
            $table->string('issuer', 255)->comment('OIDC issuer canonical identity');
            $table->string('subject', 255)->comment('OIDC subject canonical identity');
            $table->timestamps();
            $table->unique(['issuer', 'subject'], 'uq_system_user_identity_issuer_subject');
            $table->index('user_id', 'idx_system_user_identity_user');
            $table->foreign('user_id', 'fk_system_user_identity_user')
                ->references('id')
                ->on('system_user')
                ->cascadeOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / externi identity uzivatelu');
        }, ifNotExists: true);
    }
}
