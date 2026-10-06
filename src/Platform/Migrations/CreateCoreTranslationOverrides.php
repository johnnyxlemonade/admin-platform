<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari explicitni translation overrides a jejich cache revision store
 */
final class CreateCoreTranslationOverrides implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20261003120000_create_core_translation_overrides';
    }

    /**
     * Uklada pouze mutable override hodnoty, nikoli package source translations
     */
    public function up(Schema $schema): void
    {
        $schema->create('system_translation_override', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator override');
            $table->string('locale', 35)->comment('Locale explicitni hodnoty');
            $table->string('translation_group', 100)->comment('Translation group source key');
            $table->string('translation_key', 191)->comment('Flattened key v translation group');
            $table->text('value')->comment('Explicitni hodnota prekryvajici package source');
            $table->timestamps();
            $table->unique(['locale', 'translation_group', 'translation_key'], 'uq_system_translation_override_identity');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade Admin / explicitni translation overrides');
        }, ifNotExists: true);

        $schema->create('system_translation_override_revision', static function (TableBlueprint $table): void {
            $table->string('locale', 35)->comment('Locale zmenenych overrides');
            $table->string('translation_group', 100)->comment('Translation group zmenenych overrides');
            $table->unsignedBigInteger('revision')->comment('Monotonne rostouci revize pro klientskou cache');
            $table->timestamps();
            $table->primary(['locale', 'translation_group']);
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade Admin / revize translation overrides');
        }, ifNotExists: true);
    }
}
