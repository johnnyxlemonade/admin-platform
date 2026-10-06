<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install;

use Closure;
use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Authorization\PermissionSyncService;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Catalog\ModuleManifestDiscovery;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Admin\System\Users\Models\UserRoleModel;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Database\Migration\MigrationRunner;
use Lemonade\Framework\Database\Migration\MigrationStateRepository;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Throwable;

/**
 * Provadi jednotlive kroky instalace aplikace
 */
final class InstallationService
{
    private const SESSION_AUTHORIZED_AT = 'install.install.authorized_at';
    private const SESSION_COMPLETED_STEPS = 'install.install.completed_steps';
    private const AUTHORIZATION_TTL = 600;
    private const LOCK_FILE = 'cache/installer.lock';

    /**
     * Nastavuje zavislosti potrebne pro instalacni tok
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ApplicationContext $context,
        private readonly DatabaseConfig $databaseConfig,
        private readonly DatabaseConfigurationValidator $databaseConfiguration,
        private readonly ModuleCatalog $catalog,
        private readonly ModuleManifestDiscovery $discovery,
        private readonly MigrationRegistry $migrations,
        private readonly Filesystem $filesystem,
        private readonly SessionInterface $session,
        private readonly AdminAssetManifest $assets,
    ) {}

    /**
     * Zjistuje stav databaze, schematu a root uctu
     * @return array{databaseConnected:bool,databaseSchema:bool,rootAccount:bool}
     */
    public function state(): array
    {
        if ($this->databaseConfigurationError() !== null) {
            return ['databaseConnected' => false, 'databaseSchema' => false, 'rootAccount' => false];
        }

        try {
            $this->database()->select('SELECT 1');
        } catch (Throwable) {
            return ['databaseConnected' => false, 'databaseSchema' => false, 'rootAccount' => false];
        }

        try {
            $this->database()->select('SELECT 1 FROM system_user LIMIT 1');
        } catch (Throwable) {
            return ['databaseConnected' => true, 'databaseSchema' => false, 'rootAccount' => false];
        }

        try {
            $root = $this->database()->select(
                'SELECT 1 FROM system_user u INNER JOIN system_user_role ur ON ur.user_id = u.id INNER JOIN system_role r ON r.id = ur.role_id WHERE u.active = 1 AND u.deleted_at IS NULL AND r.code = ? AND r.is_super_admin = 1 AND r.deleted_at IS NULL LIMIT 1',
                ['root'],
            );
        } catch (Throwable) {
            $root = [];
        }

        return ['databaseConnected' => true, 'databaseSchema' => true, 'rootAccount' => $root !== []];
    }

    /**
     * Zpracovava krok catalogexists v instalaci aplikace
     */
    public function catalogExists(): bool
    {
        return is_file($this->context->path('vendor/composer/installed.json'));
    }

    /**
     * Zpracovava krok authorizeinstall v instalaci aplikace
     */
    public function authorizeInstall(): void
    {
        $this->session->set(self::SESSION_AUTHORIZED_AT, time());
    }

    /**
     * Zpracovava krok installisauthorized v instalaci aplikace
     */
    public function installIsAuthorized(): bool
    {
        $authorizedAt = $this->session->get(self::SESSION_AUTHORIZED_AT);

        return is_int($authorizedAt) && $authorizedAt >= time() - self::AUTHORIZATION_TTL;
    }

    /**
     * Zpracovava krok clearinstallstate v instalaci aplikace
     */
    public function clearInstallState(): void
    {
        $this->session->remove(self::SESSION_AUTHORIZED_AT);
        $this->session->remove(self::SESSION_COMPLETED_STEPS);
    }

    /**
     * Zpracovava krok completedsteps v instalaci aplikace
     * @return list<InstallStep>
     */
    public function completedSteps(): array
    {
        $stored = $this->session->get(self::SESSION_COMPLETED_STEPS, []);
        if (!is_array($stored)) {
            return [];
        }

        $steps = [];
        foreach ($stored as $value) {
            if (!is_string($value)) {
                continue;
            }
            $step = InstallStep::tryFrom($value);
            if ($step !== null) {
                $steps[] = $step;
            }
        }

        return array_values(array_unique($steps, SORT_REGULAR));
    }

    /**
     * Kontroluje podminky nutne pred spustenim instalace
     * @return array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>}
     */
    public function preflight(): array
    {
        $checks = [];
        $checks[] = $this->check('php', version_compare(PHP_VERSION, '8.1.0', '>=') && version_compare(PHP_VERSION, '8.6.0', '<'), 'install.installErrors.php');
        foreach (['fileinfo', 'mbstring', 'pdo', 'pdo_mysql'] as $extension) {
            $checks[] = $this->check('extension.' . $extension, extension_loaded($extension), 'install.installErrors.extension.' . $extension);
        }

        $databaseConfigurationError = $this->databaseConfigurationError();
        $checks[] = $this->check(
            'database.configuration',
            $databaseConfigurationError === null,
            $databaseConfigurationError ?? '',
        );
        if ($databaseConfigurationError !== null) {
            $checks[] = $this->check(
                'database.connection',
                false,
                'install.installErrors.database_skipped',
                'skipped',
            );
        } else {
            try {
                $this->database()->select('SELECT 1');
                $checks[] = $this->check('database.connection', true, '');
            } catch (Throwable $exception) {
                $checks[] = $this->check('database.connection', false, $this->safeError($exception));
            }
        }

        $storage = $this->context->storagePath();
        $cache = $this->context->resolveCachePath();
        $storageWritable = is_dir($storage) ? is_writable($storage) : is_writable(dirname($storage));
        $cacheWritable = is_dir($cache) ? is_writable($cache) : $storageWritable;
        $checks[] = $this->check('storage', $storageWritable, 'install.installErrors.storage');
        $checks[] = $this->check('cache', $cacheWritable, 'install.installErrors.cache');

        $checks[] = $this->check('vendor', is_file($this->context->path('vendor/autoload.php')), 'install.installErrors.vendor');
        $checks[] = $this->check('assets.admin_css', $this->assets->hasStyles('admin') && $this->assets->hasStyles('vendor'), 'install.installErrors.assets.admin_css');
        $checks[] = $this->check('assets.admin_js', $this->assets->hasScript('admin'), 'install.installErrors.assets.admin_js');

        return [
            'ready' => array_reduce($checks, static fn(bool $ready, array $check): bool => $ready && $check['ok'], true),
            'checks' => $checks,
        ];
    }

    /**
     * Zpracovava krok discovercatalog v instalaci aplikace
     */
    public function discoverCatalog(): void
    {
        $this->locked(function (): void {
            $this->discovery->writeCatalog($this->catalog);
        });
    }

    /**
     * Zpracovava krok executestep v instalaci aplikace
     * @return array{step:InstallStep,status:InstallStepStatus,messageKey:string,nextStep:InstallStep|null,rootCreated:bool}
     */
    public function executeStep(InstallStep $step, bool $initialInstall, ?string $email = null, ?string $password = null): array
    {
        $preflight = $this->preflight();
        if (!$preflight['ready']) {
            foreach ($preflight['checks'] as $check) {
                if (!$check['ok']) {
                    return $this->result($step, InstallStepStatus::Error, $check['detail']);
                }
            }
        }

        try {
            return $this->locked(function () use ($step, $initialInstall, $email, $password): array {
                if ($step === InstallStep::Discovery) {
                    $this->discovery->writeCatalog($this->catalog);

                    return $this->result($step, InstallStepStatus::Success, 'install.install.result.success', InstallStep::Database);
                }
                if (!$this->catalogExists()) {
                    return $this->result($step, InstallStepStatus::Error, 'install.installErrors.catalog');
                }
                if ($this->isCompleted($step)) {
                    return $this->result($step, InstallStepStatus::Skipped, 'install.installSteps.skipped', $this->nextStepFor($step, $initialInstall));
                }
                if (!$this->priorStepsCompleted($step)) {
                    return $this->result($step, InstallStepStatus::Error, 'install.installErrors.step_order');
                }

                if ($step === InstallStep::Root && !$initialInstall) {
                    return $this->result($step, InstallStepStatus::Skipped, 'install.installSteps.skipped');
                }
                if ($step === InstallStep::Root && ($email === null || $password === null)) {
                    return $this->result($step, InstallStepStatus::Error, 'install.installErrors.credentials');
                }

                if ($step === InstallStep::Root) {
                    $this->users()->createInitialRoot((string) $email, (string) $password);
                    $this->markCompleted($step);

                    return $this->result(
                        $step,
                        InstallStepStatus::Success,
                        'install.install.result.success',
                        $this->nextStepFor($step, $initialInstall),
                        true,
                    );
                }

                switch ($step) {
                    case InstallStep::Database:
                        (new MigrationRunner($this->migrations, $this->migrationState(), $this->schema()))->migrate();
                        break;
                    case InstallStep::Modules:
                        $this->modules()->migrateInstalled(AuditActor::migration('web-installer'));
                        break;
                    case InstallStep::Permissions:
                        $this->permissions()->sync();
                        if ($initialInstall) {
                            $this->roles()->ensureCanonicalSystemRoles();
                            $this->roles()->synchronizeCanonicalSystemRolePermissions();
                        }
                        break;
                }
                $this->markCompleted($step);

                return $this->result(
                    $step,
                    InstallStepStatus::Success,
                    'install.install.result.success',
                    $this->nextStepFor($step, $initialInstall),
                    false,
                );
            });
        } catch (Throwable $exception) {
            return $this->result($step, InstallStepStatus::Error, $this->safeError($exception));
        }
    }

    /**
     * Spousti pozadovany krok instalace
     * @return array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool}
     */
    public function run(bool $initialInstall, ?string $email = null, ?string $password = null): array
    {
        $preflight = $this->preflight();
        $steps = [];
        foreach ($preflight['checks'] as $check) {
            $steps[] = ['key' => 'preflight.' . $check['key'], 'ok' => $check['ok'], 'error' => $check['ok'] ? '' : $check['detail']];
        }
        if (!$preflight['ready']) {
            return ['success' => false, 'steps' => $steps, 'rootCreated' => false];
        }
        if (!$this->catalogExists()) {
            $steps[] = ['key' => InstallStep::Discovery->value, 'ok' => false, 'error' => 'install.installErrors.catalog'];

            return ['success' => false, 'steps' => $steps, 'rootCreated' => false];
        }

        $discoveryCompleted = $this->isCompleted(InstallStep::Discovery);
        $steps[] = [
            'key' => InstallStep::Discovery->value,
            'ok' => true,
            'error' => $discoveryCompleted ? '' : 'install.installSteps.skipped',
        ];
        $rootCreated = false;
        foreach ([InstallStep::Database, InstallStep::Modules, InstallStep::Permissions] as $step) {
            $result = $this->executeStep($step, $initialInstall);
            $steps[] = $this->stepRecord($result);
            if ($result['status'] === InstallStepStatus::Error) {
                return ['success' => false, 'steps' => $steps, 'rootCreated' => false];
            }
        }
        if ($initialInstall) {
            $result = $this->executeStep(InstallStep::Root, true, $email, $password);
            $steps[] = $this->stepRecord($result);
            $rootCreated = $result['rootCreated'];
            if ($result['status'] === InstallStepStatus::Error) {
                return ['success' => false, 'steps' => $steps, 'rootCreated' => false];
            }
        } else {
            $steps[] = ['key' => InstallStep::Root->value, 'ok' => true, 'error' => 'install.installSteps.skipped'];
        }

        $this->clearInstallState();

        return ['success' => true, 'steps' => $steps, 'rootCreated' => $rootCreated];
    }

    /**
     * Zpracovava krok stepstates v instalaci aplikace
     * @return list<array{step:string,status:InstallStepStatus,messageKey:string}>
     */
    public function stepStates(bool $initialInstall): array
    {
        $completed = $this->completedSteps();
        $states = [];
        foreach (InstallStep::pipeline() as $step) {
            if ($step === InstallStep::Root && !$initialInstall) {
                $status = InstallStepStatus::Skipped;
            } elseif (in_array($step, $completed, true)) {
                $status = InstallStepStatus::Success;
            } elseif ($step === InstallStep::Discovery && $this->catalogExists()) {
                $status = InstallStepStatus::Success;
            } else {
                $status = InstallStepStatus::Pending;
            }
            $states[] = ['step' => $step->value, 'status' => $status, 'messageKey' => ''];
        }

        return $states;
    }

    /**
     * Zpracovava krok steprecord v instalaci aplikace
     * @param array{step:InstallStep,status:InstallStepStatus,messageKey:string,nextStep:InstallStep|null,rootCreated:bool} $result
     * @return array{key:string,ok:bool,error:string}
     */
    private function stepRecord(array $result): array
    {
        return [
            'key' => $result['step']->value,
            'ok' => $result['status'] !== InstallStepStatus::Error,
            'error' => $result['status'] === InstallStepStatus::Error ? $result['messageKey'] : '',
        ];
    }

    /**
     * Zpracovava krok result v instalaci aplikace
     * @return array{step:InstallStep,status:InstallStepStatus,messageKey:string,nextStep:InstallStep|null,rootCreated:bool}
     */
    private function result(InstallStep $step, InstallStepStatus $status, string $messageKey, ?InstallStep $nextStep = null, bool $rootCreated = false): array
    {
        return ['step' => $step, 'status' => $status, 'messageKey' => $messageKey, 'nextStep' => $nextStep, 'rootCreated' => $rootCreated];
    }

    /**
     * Rozhoduje, zda plati podminka iscompleted
     */
    private function isCompleted(InstallStep $step): bool
    {
        return in_array($step, $this->completedSteps(), true);
    }

    /**
     * Zpracovava krok priorstepscompleted v instalaci aplikace
     */
    private function priorStepsCompleted(InstallStep $step): bool
    {
        $completed = $this->completedSteps();
        foreach (InstallStep::pipeline() as $candidate) {
            if ($candidate === $step) {
                return true;
            }
            if ($candidate === InstallStep::Discovery && $this->catalogExists()) {
                continue;
            }
            if (!in_array($candidate, $completed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Zpracovava krok nextstepfor v instalaci aplikace
     */
    private function nextStepFor(InstallStep $step, bool $initialInstall): ?InstallStep
    {
        $next = $step->next();
        if (!$initialInstall && $next === InstallStep::Root) {
            return null;
        }

        return $next;
    }

    /**
     * Zpracovava krok markcompleted v instalaci aplikace
     */
    private function markCompleted(InstallStep $step): void
    {
        $steps = $this->completedSteps();
        if (!in_array($step, $steps, true)) {
            $steps[] = $step;
        }
        $this->session->set(self::SESSION_COMPLETED_STEPS, array_map(static fn(InstallStep $item): string => $item->value, $steps));
    }

    /**
     * Zpracovava krok check v instalaci aplikace
     * @return array{key:string,ok:bool,status:string,detail:string}
     */
    private function check(string $key, bool $ok, string $detail, string $status = 'success'): array
    {
        return ['key' => $key, 'ok' => $ok, 'status' => $status === 'skipped' ? 'skipped' : ($ok ? 'success' : 'error'), 'detail' => $detail];
    }

    /**
     * Zpracovava krok databaseconfigurationerror v instalaci aplikace
     */
    private function databaseConfigurationError(): ?string
    {
        $errorCode = $this->databaseConfiguration->errorCode($this->databaseConfig);

        if ($errorCode !== null) {
            return 'install.installErrors.database_' . $errorCode;
        }

        return null;
    }

    /**
     * Zpracovava krok database v instalaci aplikace
     */
    private function database(): Database
    {
        return $this->container->get(Database::class);
    }

    /**
     * Zpracovava krok migrationstate v instalaci aplikace
     */
    private function migrationState(): MigrationStateRepository
    {
        return $this->container->get(MigrationStateRepository::class);
    }

    /**
     * Zpracovava krok schema v instalaci aplikace
     */
    private function schema(): Schema
    {
        return $this->container->get(Schema::class);
    }

    /**
     * Zpracovava krok modules v instalaci aplikace
     */
    private function modules(): ModuleLifecycleService
    {
        return $this->container->get(ModuleLifecycleService::class);
    }

    /**
     * Zpracovava krok permissions v instalaci aplikace
     */
    private function permissions(): PermissionSyncService
    {
        return $this->container->get(PermissionSyncService::class);
    }

    /**
     * Zpracovava krok users v instalaci aplikace
     */
    private function users(): UserService
    {
        return $this->container->get(UserService::class);
    }

    /**
     * Zpracovava krok roles v instalaci aplikace
     */
    private function roles(): UserRoleModel
    {
        return $this->container->get(UserRoleModel::class);
    }

    /**
     * Zpracovava krok locked v instalaci aplikace
     * @param Closure(): mixed $action
     */
    private function locked(Closure $action): mixed
    {
        return $this->filesystem->lock($this->context->storagePath(self::LOCK_FILE), $action);
    }

    /**
     * Zpracovava krok safeerror v instalaci aplikace
     */
    private function safeError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'install.installErrors.operation_failed';
        }
        foreach (['users.validation.email_taken', 'users.validation.email_invalid', 'users.validation.password_min_length'] as $key) {
            if (str_contains($message, $key)) {
                return $key;
            }
        }
        if (preg_match('/(?:mysql:|pgsql:|sqlite:|dsn\s*[=:]|sqlstate\[|database connection failed)/i', $message) === 1) {
            return 'install.installErrors.database';
        }

        return preg_replace('/(password|passwd|pwd|secret|token)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? 'install.installErrors.operation_failed';
    }
}
