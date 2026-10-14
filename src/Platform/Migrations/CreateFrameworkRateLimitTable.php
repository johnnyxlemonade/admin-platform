<?php

declare(strict_types=1);

namespace Lemonade\Admin\Platform\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari technicke buckety frameworkoveho rate limiteru pro Admin autentizaci
 */
final readonly class CreateFrameworkRateLimitTable implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20261013170000_create_framework_rate_limit';
    }

    /**
     * Vytvari tabulku HMAC fixed-window bucketu pro frameworkovy limiter
     */
    public function up(Schema $schema): void
    {
        $schema->create('framework_rate_limit', static function (TableBlueprint $table): void {
            $table->binary('key_hash', 32)->primary();
            $table->unsignedInteger('attempts');
            $table->datetime('expires_at');
            $table->index('expires_at', 'idx_framework_rate_limit_expires_at');
            $table->engine('InnoDB');
            $table->comment('Lemonade Framework / HMAC fixed-window rate limit buckets');
        }, ifNotExists: true);
    }
}
