<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Integration\Cms;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogService;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Cms\Content\CmsAdminRuntime;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalUserDirectory;
use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Cms\Content\CmsContentDefinition;
use Lemonade\Cms\Content\CmsContentInput;
use Lemonade\Cms\Content\CmsContentLifecycle;
use Lemonade\Cms\Content\CmsContentSchema;
use Lemonade\Cms\Content\CmsDomainMapping;
use Lemonade\Cms\Content\CmsLocalized;
use Lemonade\Cms\Content\CmsRoot;
use Lemonade\Cms\Content\Models\CmsContentModel;
use Lemonade\Cms\Routing\Cms\CmsReservedPathGuard;
use Lemonade\Cms\Routing\Cms\CmsRouteCandidateResolver;
use Lemonade\Cms\Routing\Cms\CmsRouteRepository;
use Lemonade\Cms\Routing\Cms\CmsRouteReservationService;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Framework\Cache\CacheManager;
use Lemonade\Framework\Cache\Store\ArrayCacheItemPool;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseDriver;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Support\Slug\Slugger;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Overuje CMS mutace proti oddelene testovaci databazi
 */
final class CmsContentLifecycleTest extends TestCase
{
    public function testLifecyclePersistsContentAndRollsBackFailedAudit(): void
    {
        $databaseName = getenv('CMS_INTEGRATION_DATABASE');
        if (!is_string($databaseName) || !str_starts_with($databaseName, 'cms_content_lifecycle_test_')) {
            self::fail('Set CMS_INTEGRATION_DATABASE to a dedicated cms_content_lifecycle_test_ database.');
        }
        $config = DatabaseConfig::fromArray([
            'driver' => 'mysql', 'host' => 'db', 'database' => $databaseName,
            'username' => 'db', 'password' => 'db',
        ]);
        $connection = new MysqlConnection($config, new Benchmark());
        $escaper = new MysqlIdentifierEscaper('');
        $driver = new MysqlDatabaseDriver($connection, $config, $escaper, new IdentifierProtector($escaper));
        $database = new Database($connection, $driver);
        $database->statement('CREATE TABLE IF NOT EXISTS system_user (id BIGINT UNSIGNED PRIMARY KEY, deleted_at DATETIME NULL) ENGINE=InnoDB');
        $database->statement('CREATE TABLE IF NOT EXISTS system_language (code VARCHAR(10) PRIMARY KEY, name VARCHAR(100) NOT NULL, enabled TINYINT NOT NULL, is_default TINYINT NOT NULL, sort_order INT NOT NULL) ENGINE=InnoDB');
        $database->statement('CREATE TABLE IF NOT EXISTS system_audit_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_code VARCHAR(255), module_code VARCHAR(255), entity_type VARCHAR(255), entity_key VARCHAR(255), actor_type VARCHAR(30), actor_key VARCHAR(255), actor_user_id BIGINT UNSIGNED, payload TEXT, created_at DATETIME) ENGINE=InnoDB');
        $database->statement('CREATE TABLE IF NOT EXISTS cms_route (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, module_code VARCHAR(100), entity_id BIGINT UNSIGNED, locale VARCHAR(10), path VARCHAR(700), created_at DATETIME, updated_at DATETIME, deleted_at DATETIME NULL, UNIQUE KEY uq_cms_route_target (module_code,entity_id,locale), UNIQUE KEY uq_cms_route_locale_path (locale,path)) ENGINE=InnoDB');
        $database->statement('INSERT IGNORE INTO system_user (id) VALUES (7), (9)');
        $database->statement("INSERT IGNORE INTO system_language (code,name,enabled,is_default,sort_order) VALUES ('cs','Czech',1,1,1), ('en','English',1,0,2), ('de','German',1,0,3)");

        $suffix = bin2hex(random_bytes(4));
        $root = 'cms_it_' . $suffix;
        $translation = $root . '_translation';
        $tags = $translation . '_tag';
        $moduleCode = 'cms.test' . $suffix;
        $schema = new Schema(new SchemaCompiler(new MysqlSchemaGrammar(new MysqlSqlEscaper($escaper), $config)), $driver);
        $schema->create($root, static function (TableBlueprint $table) use ($root): void {
            CmsContentSchema::root($table, $root);
            $table->decimal('price', 15, 2)->nullable();
        });
        $schema->create($translation, static function (TableBlueprint $table) use ($root): void {
            CmsContentSchema::translation($table, $root, 'article_id');
            $table->string('location', 255)->nullable();
        });
        $schema->create($tags, static function (TableBlueprint $table) use ($root, $translation): void {
            CmsContentSchema::tags($table, $root, $translation);
        });
        $definition = new CmsContentDefinition($moduleCode, $root, $root, $translation, 'article_id', $tags, 'uq_' . $root . '_translation_locale', 'test', IntegrationDomainData::class);
        $lifecycle = $this->lifecycle($database, $definition, new AuditLogService($database));
        $id = $lifecycle->createDraft(7, 'cs');
        $input = CmsContentInput::fromEditor([
            'locale' => 'cs', 'title' => 'Prvni zaznam ' . $suffix, 'tags' => ['Praha', 'Brno', 'Praha'],
            'publication_state' => '2', 'published_at' => '2028-01-02T10:30',
        ]);
        $data = new IntegrationDomainData('1234567890123.50', 'Praha');
        $lifecycle->save(7, $id, $input, $data);
        $rootRow = $this->requireRow($database->table($root)->where('id', $id)->first());
        $row = $this->requireRow($database->table($translation)->where('article_id', $id)->where('locale', 'cs')->first());
        self::assertSame('1234567890123.50', $rootRow['price']);
        self::assertSame('Praha', $row['location']);
        self::assertSame('2028-01-02 10:30:00', $row['published_at']);
        self::assertSame(['Praha', 'Brno'], array_column($database->table($tags)->where('translation_id', $row['id'])->orderBy('sort_order', 'ASC')->getArray(), 'name'));
        $audits = $database->table('system_audit_log')->where('module_code', $moduleCode)->getArray();
        self::assertSame([$moduleCode . '.created', $moduleCode . '.updated', $moduleCode . '.translation_updated', $moduleCode . '.published'], array_column($audits, 'event_code'));
        $lifecycle->save(7, $id, $input, $data);
        self::assertCount(4, $database->table('system_audit_log')->where('module_code', $moduleCode)->getArray());
        $secondId = $lifecycle->createDraft(7, 'cs');
        $lifecycle->save(7, $secondId, CmsContentInput::fromEditor([
            'locale' => 'cs', 'title' => 'Prvni zaznam ' . $suffix,
        ]), new IntegrationDomainData(null, null));
        $secondRoute = $this->requireRow($database->table('cms_route')->where('module_code', $moduleCode)->where('entity_id', $secondId)->where('locale', 'cs')->first());
        self::assertSame('prvni-zaznam-' . $suffix . '-1', $secondRoute['path']);
        $lifecycle->changeUrlSegment(7, $id, 'cs', 'změněná-cesta-' . $suffix);
        $lifecycle->transferOwner(7, $id, 9);
        $lifecycle->save(7, $id, CmsContentInput::fromEditor(['locale' => 'en', 'title' => 'English ' . $suffix]), new IntegrationDomainData('1234567890123.50', 'London'));
        self::assertSame('London', $this->requireRow($database->table($translation)->where('article_id', $id)->where('locale', 'en')->first())['location']);
        $lifecycle->delete(7, $id);
        $lifecycle->restore(7, $id);
        self::assertSame(1, (int) $this->requireRow($database->table($translation)->where('article_id', $id)->where('locale', 'cs')->first())['publication_state']);
        self::assertSame(9, (int) $this->requireRow($database->table($root)->where('id', $id)->first())['owner_id']);

        $failingWriter = new class implements AuditLogWriterInterface {
            public function record(DomainEvent $event, AuditActor $actor): void
            {
                throw new RuntimeException('Audit failure');
            }
        };
        $failingLifecycle = $this->lifecycle($database, $definition, $failingWriter);
        try {
            $failingLifecycle->save(7, $id, CmsContentInput::fromEditor(['locale' => 'de', 'title' => 'Changed ' . $suffix]), new IntegrationDomainData('2.00', 'Brno'));
            self::fail('Audit failure must abort the transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit failure', $exception->getMessage());
        }
        self::assertSame('1234567890123.50', $this->requireRow($database->table($root)->where('id', $id)->first())['price']);
        self::assertSame('Praha', $this->requireRow($database->table($translation)->where('article_id', $id)->where('locale', 'cs')->first())['location']);
        self::assertNull($database->table($translation)->where('article_id', $id)->where('locale', 'de')->first());
        self::assertNull($database->table('cms_route')->where('module_code', $moduleCode)->where('entity_id', $id)->where('locale', 'de')->first());
    }

    /**
     * Vrati existujici radek pro kontrolu ulozenych hodnot
     *
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private function requireRow(?array $row): array
    {
        self::assertNotNull($row);

        return $row;
    }

    /**
     * Pripoji lifecycle k testovaci DB a skutecnemu Admin auditu
     *
     * @return CmsContentLifecycle<IntegrationDomainData>
     */
    private function lifecycle(Database $database, CmsContentDefinition $definition, AuditLogWriterInterface $writer): CmsContentLifecycle
    {
        $events = new TransactionalEventProcessor($database, $writer, new DomainEventDispatcher(new NullLogger()));
        $languages = new LanguageRegistry($database, new CacheManager(new ArrayCacheItemPool()));
        $runtime = new CmsAdminRuntime($events, $languages, new LocalUserDirectory($database));
        $locales = new class implements PublicLocaleRegistryInterface {
            public function snapshot(): PublicLocaleSnapshot
            {
                return new PublicLocaleSnapshot('cs', ['cs', 'en', 'de'], ['cs', 'en', 'de']);
            }
        };
        $guard = new CmsReservedPathGuard(new Router(), new ServerRequest('GET', '/'), $locales);
        $routes = new CmsRouteReservationService(new CmsRouteRepository($database), new CmsRouteCandidateResolver(), $guard);

        return new CmsContentLifecycle($definition, new CmsContentModel($database, $definition), new CmsDomainMapping(IntegrationDomainData::class), $routes, new Slugger(), $runtime);
    }
}

/**
 * Mapuje domenova pole pro integracni CMS test
 */
final readonly class IntegrationDomainData
{
    /**
     * Nastavuje presnou cenu a lokalizovanou lokalitu
     */
    public function __construct(
        #[CmsRoot('price', decimalScale: 2)]
        public ?string $price,
        #[CmsLocalized('location')]
        public ?string $location,
    ) {}
}
