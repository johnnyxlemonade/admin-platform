<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Integration\Cms;

use Lemonade\Admin\Audit\AuditLogService;
use Lemonade\Admin\Cms\Content\CmsAdminRuntime;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalUserDirectory;
use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\Migration\ModuleMigrationManifestInterface;
use Lemonade\Cms\Content\CmsContentDefinition;
use Lemonade\Cms\Content\CmsContentInput;
use Lemonade\Cms\Content\CmsContentLifecycle;
use Lemonade\Cms\Content\CmsDomainMapping;
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
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Support\Slug\Slugger;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;

/**
 * Overuje skutecna schema News, Auction a Rental proti spolecnemu CMS lifecycle
 */
final class CmsModuleLifecycleTest extends TestCase
{
    /**
     * @dataProvider modules
     * @param array<string,mixed> $domainInput
     */
    public function testFreshModuleSchemaAndSharedLifecycle(
        string $manifestClass,
        string $dataClass,
        string $module,
        string $root,
        string $translation,
        string $tags,
        string $prefix,
        array $domainInput,
    ): void {
        $databaseName = getenv('CMS_INTEGRATION_DATABASE');
        if (!is_string($databaseName) || !str_starts_with($databaseName, 'cms_content_modules_test_')) {
            self::fail('CMS_INTEGRATION_DATABASE must name a dedicated cms_content_modules_test_ database.');
        }
        self::assertTrue(class_exists($manifestClass));
        self::assertTrue(class_exists($dataClass));
        $manifest = new $manifestClass();
        self::assertInstanceOf(ModuleManifestInterface::class, $manifest);
        self::assertInstanceOf(ModuleMigrationManifestInterface::class, $manifest);
        self::assertSame($module, $manifest->code());

        $config = DatabaseConfig::fromArray(['driver' => 'mysql', 'host' => 'db', 'database' => $databaseName, 'username' => 'db', 'password' => 'db']);
        $connection = new MysqlConnection($config, new Benchmark());
        $escaper = new MysqlIdentifierEscaper('');
        $driver = new MysqlDatabaseDriver($connection, $config, $escaper, new IdentifierProtector($escaper));
        $database = new Database($connection, $driver);
        $database->statement('CREATE TABLE IF NOT EXISTS system_user (id BIGINT UNSIGNED PRIMARY KEY, deleted_at DATETIME NULL) ENGINE=InnoDB');
        $database->statement('CREATE TABLE IF NOT EXISTS system_language (code VARCHAR(10) PRIMARY KEY, name VARCHAR(100) NOT NULL, enabled TINYINT NOT NULL, is_default TINYINT NOT NULL, sort_order INT NOT NULL) ENGINE=InnoDB');
        $database->statement('CREATE TABLE IF NOT EXISTS system_audit_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_code VARCHAR(255), module_code VARCHAR(255), entity_type VARCHAR(255), entity_key VARCHAR(255), actor_type VARCHAR(30), actor_key VARCHAR(255), actor_user_id BIGINT UNSIGNED, payload TEXT, created_at DATETIME) ENGINE=InnoDB');
        $database->statement('CREATE TABLE IF NOT EXISTS cms_route (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, module_code VARCHAR(100), entity_id BIGINT UNSIGNED, locale VARCHAR(10), path VARCHAR(700), created_at DATETIME, updated_at DATETIME, deleted_at DATETIME NULL, UNIQUE KEY uq_cms_route_target (module_code,entity_id,locale), UNIQUE KEY uq_cms_route_locale_path (locale,path)) ENGINE=InnoDB');
        $database->statement('INSERT IGNORE INTO system_user (id) VALUES (7)');
        $database->statement("INSERT IGNORE INTO system_language (code,name,enabled,is_default,sort_order) VALUES ('cs','Czech',1,1,1), ('en','English',1,0,2)");
        $schema = new Schema(new SchemaCompiler(new MysqlSchemaGrammar(new MysqlSqlEscaper($escaper), $config)), $driver);
        foreach ($manifest->migrations() as $migrationClass) {
            (new $migrationClass())->up($schema);
        }
        $definition = new CmsContentDefinition($module, $root, $root, $translation, 'article_id', $tags, 'uq_' . $root . '_translation_locale', $prefix, $dataClass);
        $events = new TransactionalEventProcessor($database, new AuditLogService($database), new DomainEventDispatcher(new NullLogger()));
        $languages = new LanguageRegistry($database, new CacheManager(new ArrayCacheItemPool()));
        $runtime = new CmsAdminRuntime($events, $languages, new LocalUserDirectory($database));
        $locales = new class implements PublicLocaleRegistryInterface {
            public function snapshot(): PublicLocaleSnapshot
            {
                return new PublicLocaleSnapshot('cs', ['cs', 'en'], ['cs', 'en']);
            }
        };
        $guard = new CmsReservedPathGuard(new Router(), new ServerRequest('GET', '/'), $locales);
        $routes = new CmsRouteReservationService(new CmsRouteRepository($database), new CmsRouteCandidateResolver(), $guard);
        $service = new CmsContentLifecycle($definition, new CmsContentModel($database, $definition), new CmsDomainMapping($dataClass), $routes, new Slugger(), $runtime);
        $data = (new ReflectionMethod($dataClass, 'fromEditor'))->invoke(null, $domainInput);
        self::assertIsObject($data);
        $id = $service->createDraft(7, 'cs');
        $draft = $database->table($root)->where('id', $id)->first() ?? [];
        foreach (CmsContentInput::DISPLAY_DEFAULTS as $column => $default) {
            self::assertSame($default, (int) ($draft[$column] ?? -1));
        }
        $title = 'Integration ' . $prefix . ' ' . bin2hex(random_bytes(4));
        $input = CmsContentInput::fromEditor([
            'locale' => 'cs', 'title' => $title, 'tags' => ['První', 'Druhý', 'První'],
            'publication_state' => '2', 'published_at' => '2028-02-01T12:00',
            'recommended' => '1', 'show_author' => '0', 'sharing_enabled' => '0',
            'show_published_at' => '0', 'show_thumbnail' => '0', 'show_reading_time' => '0', 'show_tags' => '0',
        ]);
        $service->save(7, $id, $input, $data);
        $savedRoot = $database->table($root)->where('id', $id)->first() ?? [];
        self::assertSame(1, (int) ($savedRoot['recommended'] ?? -1));
        foreach (array_keys(CmsContentInput::DISPLAY_DEFAULTS) as $column) {
            if ($column !== 'recommended') {
                self::assertSame(0, (int) ($savedRoot[$column] ?? -1));
            }
        }
        $row = $database->table($translation)->where('article_id', $id)->where('locale', 'cs')->first();
        self::assertNotNull($row);
        self::assertSame('2028-02-01 12:00:00', $row['published_at']);
        self::assertSame(['První', 'Druhý'], array_column($database->table($tags)->where('translation_id', $row['id'])->orderBy('sort_order', 'ASC')->getArray(), 'name'));
        $before = $database->table('system_audit_log')->where('module_code', $module)->where('entity_key', (string) $id)->getArray();
        $service->save(7, $id, $input, $data);
        $after = $database->table('system_audit_log')->where('module_code', $module)->where('entity_key', (string) $id)->getArray();
        self::assertCount(count($before), $after);
        self::assertContains($module . '.published', array_column($after, 'event_code'));
        $route = $database->table('cms_route')->where('module_code', $module)->where('entity_id', $id)->where('locale', 'cs')->first();
        self::assertNotNull($route);
        if ($prefix === 'auction') {
            $rootRow = $database->table($root)->where('id', $id)->first();
            self::assertNotNull($rootRow);
            self::assertSame('1234567890123.50', $rootRow['starting_price']);
            self::assertSame('Praha', $row['location']);
        }
    }

    /**
     * Poskytuje realne manifesty a domenova data vsech tri modulu
     *
     * @return iterable<string,array{string,string,string,string,string,string,string,array<string,mixed>}>
     */
    public static function modules(): iterable
    {
        yield 'news' => [
            'Lemonade\\Cms\\News\\ModuleManifest', 'Lemonade\\Cms\\News\\NewsData', 'cms.news',
            'cms_news_article', 'cms_news_article_translation', 'cms_news_article_translation_tag', 'news',
            ['author_name' => 'Jana', 'recommended' => '1'],
        ];
        yield 'rental' => [
            'Lemonade\\Cms\\Rental\\ModuleManifest', 'Lemonade\\Cms\\Rental\\RentalData', 'cms.rental',
            'cms_rental', 'cms_rental_translation', 'cms_rental_translation_tag', 'rental',
            ['monthly_rent' => '1234567890123.5', 'offer_state' => 'available', 'location' => 'Praha'],
        ];
        yield 'auction' => [
            'Lemonade\\Cms\\Auction\\ModuleManifest', 'Lemonade\\Cms\\Auction\\AuctionData', 'cms.auction',
            'cms_auction', 'cms_auction_translation', 'cms_auction_translation_tag', 'auction',
            ['starting_price' => '1234567890123.5', 'auction_state' => 'active', 'location' => 'Praha'],
        ];
    }
}
