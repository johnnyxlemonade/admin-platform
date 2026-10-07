<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Migrations;

use Lemonade\Admin\Migrations\CreateDashboardWidgets;
use Lemonade\Admin\Migrations\CreateNotifications;
use Lemonade\Admin\Platform\Migrations\CreateCoreAuditLog;
use Lemonade\Admin\Platform\Migrations\CreateCoreAuthorizationAssignments;
use Lemonade\Admin\Platform\Migrations\CreateCoreAuthorizationCatalog;
use Lemonade\Admin\Platform\Migrations\CreateCoreEditorLocks;
use Lemonade\Admin\Platform\Migrations\CreateCoreLanguages;
use Lemonade\Admin\Platform\Migrations\CreateCoreModuleCatalog;
use Lemonade\Admin\Platform\Migrations\CreateCoreModuleRoutePrefixes;
use Lemonade\Admin\Platform\Migrations\CreateCoreUserIdentities;
use Lemonade\Admin\Platform\Migrations\CreateCoreUsers;
use Lemonade\Cms\Migrations\CreateCoreCmsRoutes;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use PHPUnit\Framework\TestCase;

final class BaselineAuditColumnsTest extends TestCase
{
    public function testCanonicalCoreMigrationOrderUsesDomainBoundaries(): void
    {
        self::assertSame([
            '20260914170000_create_core_languages',
            '20260914170100_create_core_module_catalog',
            '20260914170200_create_core_module_route_prefixes',
            '20260914170300_create_core_users',
            '20260914170400_create_core_user_identities',
            '20260914170500_create_core_authorization_catalog',
            '20260914170600_create_core_authorization_assignments',
            '20260914170700_create_core_audit_log',
            '20260914170800_create_core_editor_locks',
            '20260914170900_create_core_cms_routes',
        ], [
            CreateCoreLanguages::identifier(),
            CreateCoreModuleCatalog::identifier(),
            CreateCoreModuleRoutePrefixes::identifier(),
            CreateCoreUsers::identifier(),
            CreateCoreUserIdentities::identifier(),
            CreateCoreAuthorizationCatalog::identifier(),
            CreateCoreAuthorizationAssignments::identifier(),
            CreateCoreAuditLog::identifier(),
            CreateCoreEditorLocks::identifier(),
            CreateCoreCmsRoutes::identifier(),
        ]);
    }

    public function testEveryApplicationTableHasNullableAuditColumns(): void
    {
        $statements = $this->createStatements();
        unset($statements['system_user_identity']);

        foreach ($statements as $table => $statement) {
            self::assertMatchesRegularExpression('/`created_at` DATETIME NULL/', $statement, $table);
            self::assertMatchesRegularExpression('/`updated_at` DATETIME NULL/', $statement, $table);
            self::assertMatchesRegularExpression('/`deleted_at` DATETIME NULL/', $statement, $table);
        }
    }

    /** @return array<string, string> */
    private function createStatements(): array
    {
        $statements = [];
        $database = $this->createMock(DatabaseDriverInterface::class);
        $database
            ->method('query')
            ->willReturnCallback(static function (string $statement) use (&$statements): bool {
                if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`/', $statement, $matches) === 1) {
                    $statements[$matches[1]] = $statement;
                }

                return true;
            });

        $configuration = DatabaseConfig::fromArray(['driver' => 'mysql']);
        $identifierEscaper = new MysqlIdentifierEscaper('');
        $schema = new Schema(
            new SchemaCompiler(
                new MysqlSchemaGrammar(
                    new MysqlSqlEscaper($identifierEscaper),
                    $configuration,
                ),
            ),
            $database,
        );

        (new CreateCoreLanguages($database))->up($schema);
        (new CreateCoreModuleCatalog())->up($schema);
        (new CreateCoreModuleRoutePrefixes())->up($schema);
        (new CreateCoreUsers())->up($schema);
        (new CreateCoreUserIdentities())->up($schema);
        (new CreateCoreAuthorizationCatalog())->up($schema);
        (new CreateCoreAuthorizationAssignments())->up($schema);
        (new CreateCoreAuditLog())->up($schema);
        (new CreateCoreEditorLocks())->up($schema);
        (new CreateCoreCmsRoutes())->up($schema);
        (new CreateNotifications())->up($schema);
        (new CreateDashboardWidgets())->up($schema);

        ksort($statements);

        self::assertSame([
            'admin_dashboard_widget_preference',
            'admin_editor_lock',
            'admin_notification',
            'admin_notification_audience_role',
            'admin_notification_audience_user',
            'admin_notification_recipient',
            'cms_route',
            'system_audit_log',
            'system_language',
            'system_module',
            'system_module_feature',
            'system_module_route_prefix',
            'system_permission',
            'system_role',
            'system_role_permission',
            'system_user',
            'system_user_identity',
            'system_user_permission',
            'system_user_role',
        ], array_keys($statements));

        return $statements;
    }
}
