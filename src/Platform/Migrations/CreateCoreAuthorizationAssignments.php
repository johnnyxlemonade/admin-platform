<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari prirazeni roli a permission overrides uzivatelu
 */
final class CreateCoreAuthorizationAssignments implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170600_create_core_authorization_assignments';
    }

    /**
     * Vytvari vazby mezi uzivateli, rolemi a opravnenimi
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_user_role', static function (TableBlueprint $table): void {
            $table->unsignedBigInteger('user_id')->comment('Uzivatel s prirazenou roli');
            $table->unsignedBigInteger('role_id')->comment('Role prirazena uzivateli');
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['user_id', 'role_id']);
            $table->unique('user_id', 'uq_system_user_role_user');
            $table->foreign('user_id', 'fk_system_user_role_user')
                ->references('id')
                ->on('system_user')
                ->cascadeOnDelete()
                ->restrictOnUpdate();
            $table->foreign('role_id', 'fk_system_user_role_role')
                ->references('id')
                ->on('system_role')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / prirazeni roli uzivatelum');
        }, ifNotExists: true);

        $schema->create('system_role_permission', static function (TableBlueprint $table): void {
            $table->unsignedBigInteger('role_id')->comment('Role s prirazenym opravnenim');
            $table->unsignedBigInteger('permission_id')->comment('Opravneni prirazene roli');
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id', 'fk_system_role_permission_role')
                ->references('id')
                ->on('system_role')
                ->cascadeOnDelete()
                ->restrictOnUpdate();
            $table->foreign('permission_id', 'fk_system_role_permission_permission')
                ->references('id')
                ->on('system_permission')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / prirazeni opravneni rolim');
        }, ifNotExists: true);

        $schema->create('system_user_permission', static function (TableBlueprint $table): void {
            $table->unsignedBigInteger('user_id')->comment('Uzivatel s vyjimkou opravneni');
            $table->unsignedBigInteger('permission_id')->comment('Opravneni upravene vyjimkou');
            $table->string('effect', 5)->comment('Povoleni nebo zakaz jako vyjimka vuci roli');
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['user_id', 'permission_id']);
            $table->foreign('user_id', 'fk_system_user_permission_user')
                ->references('id')
                ->on('system_user')
                ->cascadeOnDelete()
                ->restrictOnUpdate();
            $table->foreign('permission_id', 'fk_system_user_permission_permission')
                ->references('id')
                ->on('system_permission')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / vyjimky opravneni uzivatelu');
        }, ifNotExists: true);
    }
}
