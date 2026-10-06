<?php

declare(strict_types=1);

namespace Lemonade\Admin\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari tabulky pro dashboardove widgety
 */
final class CreateDashboardWidgets implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260919100000_create_admin_dashboard_widgets';
    }

    /**
     * Vytvari tabulku osobnich preferenci dashboard widgetu
     */
    public function up(Schema $schema): void
    {
        $schema->create('admin_dashboard_widget_preference', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator preference dashboard widgetu');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Lokalni uzivatel, kteremu preference patri');
            $table->string('owner_key', 191)->comment('Kanonicky vlastnik preference, bez osobnich udaju');
            $table->string('widget_code', 150)->comment('Stabilni kod dashboard widgetu');
            $table->boolean('personal_pinned')->default(0)->comment('Urcuje osobni pripnuti widgetu uzivatelem');
            $table->unsignedInteger('position')->default(0)->comment('Logicke poradi widgetu na dashboardu');
            $table->string('size', 20)->nullable()->comment('Semanticka velikost widgetu');
            $table->datetime('created_at')->nullable()->comment('Cas vytvoreni preference');
            $table->datetime('updated_at')->nullable()->comment('Cas posledni zmeny preference');
            $table->softDeletes()->comment('Cas soft-delete preference; NULL znamena aktivni preferenci');
            $table->unique(['owner_key', 'widget_code'], 'uq_dashboard_widget_preference_owner');
            $table->index(['owner_key', 'position'], 'idx_dashboard_preference_owner_layout');
            $table->foreign('user_id', 'fk_dashboard_preference_user')->references('id')->on('system_user')->restrictOnDelete()->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade Admin / osobni preference dashboard widgetu');
        }, ifNotExists: true);
    }
}
