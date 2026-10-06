<?php

declare(strict_types=1);

namespace Lemonade\Admin\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari tabulky pro oznameni a prijemce
 */
final class CreateNotifications implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260918171000_create_admin_notifications';
    }

    /**
     * Vytvari tabulky oznameni, cilovych skupin a prijemcu
     */
    public function up(Schema $schema): void
    {
        $schema->create('admin_notification', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator publikovaneho oznameni');
            $table->string('type', 20)->comment('Uzavreny typ oznameni');
            $table->string('title', 180)->comment('Kratky nadpis oznameni');
            $table->text('message')->comment('Kratky text oznameni');
            $table->unsignedBigInteger('created_by_user_id')->comment('Lokalni autor publikace');
            $table->boolean('active')->default(1)->comment('Urcuje, zda se oznameni dorucuje recipientum');
            $table->datetime('created_at')->nullable()->comment('Cas vytvoreni oznameni');
            $table->datetime('updated_at')->nullable()->comment('Cas posledni zmeny oznameni');
            $table->softDeletes()->comment('Cas soft-delete oznameni; NULL znamena existujici zaznam');
            $table->index('created_at', 'idx_notification_created');
            $table->index(['deleted_at', 'active', 'created_at'], 'idx_notification_lifecycle_created');
            $table->index(['created_by_user_id', 'created_at'], 'idx_notification_author_created');
            $table->foreign('created_by_user_id', 'fk_notification_author_user')
                ->references('id')
                ->on('system_user')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade Admin / publikovana oznameni');
        }, ifNotExists: true);
        $schema->create('admin_notification_audience_role', static function (TableBlueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('role_code', 100);
            $table->string('role_name', 150);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['notification_id', 'role_code'], 'uq_notification_audience_role');
            $table->foreign('notification_id', 'fk_notification_audience_role_notification')->references('id')->on('admin_notification')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('role_id', 'fk_notification_audience_role_role')->references('id')->on('system_role')->nullOnDelete()->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
        }, ifNotExists: true);
        $schema->create('admin_notification_audience_user', static function (TableBlueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email', 254);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['notification_id', 'user_email'], 'uq_notification_audience_user');
            $table->foreign('notification_id', 'fk_notification_audience_user_notification')->references('id')->on('admin_notification')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('user_id', 'fk_notification_audience_user_user')->references('id')->on('system_user')->nullOnDelete()->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
        }, ifNotExists: true);
        $schema->create('admin_notification_recipient', static function (TableBlueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->string('recipient_key', 191);
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->datetime('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['notification_id', 'recipient_key'], 'uq_notification_recipient');
            $table->index(['recipient_key', 'read_at', 'notification_id'], 'idx_notification_inbox');
            $table->foreign('notification_id', 'fk_notification_recipient_notification')->references('id')->on('admin_notification')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('recipient_user_id', 'fk_notification_recipient_user')->references('id')->on('system_user')->nullOnDelete()->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
        }, ifNotExists: true);
    }
}
