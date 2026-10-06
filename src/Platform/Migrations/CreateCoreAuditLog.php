<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari auditni historii lokalnich akteru a systemu
 */
final class CreateCoreAuditLog implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170700_create_core_audit_log';
    }

    /**
     * Vytvari tabulku auditni historie
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_audit_log', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator auditni udalosti');
            $table->string('event_code', 150)->comment('Stabilni kod business udalosti');
            $table->string('module_code', 100)->comment('Modul, ktereho se udalost tyka');
            $table->string('entity_type', 100)->comment('Stabilni typ zmenene entity');
            $table->string('entity_key', 191)->comment('Obecny identifikator ciloveho zaznamu');
            $table->string('actor_type', 30)->comment('Typ aktera auditni udalosti');
            $table->string('actor_key', 191)->comment('Stabilni kanonicky identifikator aktera');
            $table->unsignedBigInteger('actor_user_id')->nullable()->comment('Uzivatel, ktery zmenu provedl');
            $table->json('payload')->comment('Bezpecny strukturovany payload udalosti');
            $table->datetime('created_at')->nullable()->comment('Cas vzniku auditni udalosti');
            $table->datetime('updated_at')->nullable()->comment('Cas posledni zmeny auditni udalosti');
            $table->softDeletes();
            $table->index(['created_at'], 'idx_audit_created');
            $table->index(['module_code', 'entity_key', 'created_at'], 'idx_audit_entity_created');
            $table->index(['module_code', 'created_at'], 'idx_audit_module_created');
            $table->index(['actor_user_id', 'created_at'], 'idx_audit_actor_created');
            $table->index(['actor_type', 'actor_key', 'created_at'], 'idx_audit_actor_key_created');
            $table->index(['event_code', 'created_at'], 'idx_audit_event_created');
            $table->foreign('actor_user_id', 'fk_audit_actor_user')
                ->references('id')
                ->on('system_user')
                ->nullOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / auditni historie zmen');
        }, ifNotExists: true);
    }
}
