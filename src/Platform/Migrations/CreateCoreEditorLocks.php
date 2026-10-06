<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari vlastnicke zamky editoru
 */
final class CreateCoreEditorLocks implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170800_create_core_editor_locks';
    }

    /**
     * Vytvari tabulku vlastnickych zamku editoru
     */
    public function up(Schema $schema): void
    {
        $schema->create('admin_editor_lock', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator zamku editoru');
            $table->string('resource_type', 100)->comment('Stabilni typ zamceneho zdroje');
            $table->string('resource_id', 191)->comment('Identifikator zamceneho zdroje');
            $table->string('owner_key', 191)->comment('Kanonicka identita vlastnika zamku');
            $table->unsignedBigInteger('owner_user_id')->nullable()->comment('Lokalni uzivatel vlastnika, pokud existuje');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['resource_type', 'resource_id'], 'uq_admin_editor_lock_resource');
            $table->index('owner_key', 'idx_admin_editor_lock_owner_key');
            $table->foreign('owner_user_id', 'fk_admin_editor_lock_user')
                ->references('id')
                ->on('system_user')
                ->nullOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade Admin / aktivni vlastnicke zamky editovanych zaznamu');
        }, ifNotExists: true);
    }
}
