<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install\Http\Controller;

use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Install\InstallationService;
use Lemonade\Admin\Install\InstallStep;
use Lemonade\Admin\Install\InstallStepStatus;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\Validation\ValidationResult;
use Lemonade\Framework\Validation\ValidationSchema;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP tok instalace aplikace
 */
final class InstallController
{
    /**
     * Nastavuje zavislosti potrebne pro instalacni tok
     */
    public function __construct(
        private readonly InstallationService $installation,
        private readonly AdminUiLocale $uiLocale,
        private readonly CurrentUserProvider $currentUser,
        private readonly AuthorizationService $authorization,
        private readonly CsrfTokenManager $csrf,
        private readonly AuthPageRenderer $pages,
        private readonly Responses $responses,
        private readonly HttpRequestInspector $requests,
        private readonly TranslatorInterface $translator,
        private readonly FormValidation $validator,
        private readonly ClientTranslationVersion $translationVersion,
        private readonly Router $router,
    ) {}

    /**
     * Zobrazuje uvodni stranku instalace
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->show($request);
    }

    /**
     * Spousti pozadovany krok instalace
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        $state = $this->installation->state();
        $initial = !$state['rootAccount'];
        if (!$initial && !$this->isRootUser()) {
            return $this->notFound();
        }

        $preflight = $this->installation->preflight();
        if (!$preflight['ready']) {
            return $this->preflightFailureResponse($request);
        }

        if (!$this->installation->catalogExists()) {
            return $this->prepareCatalog($initial, $request);
        }
        if ((string) (new RequestData($request))->input('prepare', '') === '1') {
            return $this->prepareCatalog($initial, $request);
        }
        if ($initial && !$this->authorizeInitialRequest($request)) {
            return $this->requests->wantsJson($request)
                ? $this->jsonError(null, 'install.install.errors.invalid_key', HttpStatusCode::FORBIDDEN->value)
                : $this->show($request, ['generalError' => $this->translator->get('install.install.errors.invalid_key')], HttpStatusCode::FORBIDDEN->value);
        }

        return $this->requests->wantsJson($request) ? $this->runJson($initial, $request) : $this->runHtml($initial, $request);
    }

    /**
     * Zpracovava krok show v instalaci aplikace
     * @param array<string,mixed> $extra
     */
    private function show(ServerRequestInterface $request, array $extra = [], ?int $status = null): ResponseInterface
    {
        $status ??= HttpStatusCode::OK->value;
        $state = $this->installation->state();
        $completedInitialInstall = is_array($extra['result'] ?? null)
            && (($extra['result']['rootCreated'] ?? false) === true);
        if ($state['rootAccount'] && !$this->isRootUser() && !$completedInitialInstall) {
            return $this->notFound();
        }

        $requestData = new RequestData($request);
        $locale = $this->uiLocale->activate(
            (string) $requestData->query('locale', ''),
            (string) $requestData->cookie('lemonade_locale', ''),
        );
        $initial = !$state['rootAccount'];

        return $this->pages->render('admin-install::install', [
            'title' => $this->translator->get($initial ? 'install.install.title' : 'install.install.update_title'),
            'locale' => $locale,
            'installer' => true,
            'clientTranslationVersion' => $this->translationVersion,
            'installerTranslations' => $this->installerTranslations(),
            'initial' => $initial,
            'state' => $state,
            'catalogExists' => $this->installation->catalogExists(),
            'preflight' => $this->localizePreflight($this->installation->preflight()),
            'stepStates' => $this->installation->stepStates($initial),
            'errors' => $extra['errors'] ?? [],
            'generalError' => $extra['generalError'] ?? null,
            'result' => $extra['result'] ?? null,
            'email' => $extra['email'] ?? '',
        ], $status);
    }

    /**
     * Zpracovava krok preparecatalog v instalaci aplikace
     */
    private function prepareCatalog(bool $initial, ServerRequestInterface $request): ResponseInterface
    {
        if ($initial && !$this->authorizeInitialRequest($request)) {
            return $this->invalidKeyResponse($request);
        }

        try {
            $this->installation->discoverCatalog();
            if ($initial) {
                $this->installation->authorizeInstall();
            }

            return $this->responses->redirect($this->router->url('admin.install'), 303);
        } catch (\Throwable $exception) {
            return $this->show($request, [
                'generalError' => $this->localizeInstallerMessage($this->safeError($exception)),
            ], HttpStatusCode::INTERNAL_SERVER_ERROR->value);
        }
    }

    /**
     * Zpracovava krok preflightfailureresponse v instalaci aplikace
     */
    private function preflightFailureResponse(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->translator->get('install.installErrors.preflight');

        return $this->requests->wantsJson($request)
            ? $this->jsonError(null, 'install.installErrors.preflight', HttpStatusCode::UNPROCESSABLE_ENTITY->value)
            : $this->show($request, ['generalError' => $message], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Zpracovava krok invalidkeyresponse v instalaci aplikace
     */
    private function invalidKeyResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->requests->wantsJson($request)
            ? $this->jsonError(null, 'install.install.errors.invalid_key', HttpStatusCode::FORBIDDEN->value)
            : $this->show($request, ['generalError' => $this->translator->get('install.install.errors.invalid_key')], HttpStatusCode::FORBIDDEN->value);
    }

    /**
     * Zpracovava krok runhtml v instalaci aplikace
     */
    private function runHtml(bool $initial, ServerRequestInterface $request): ResponseInterface
    {
        $input = [
            'email' => (string) (new RequestData($request))->post('email', ''),
            'password' => (string) (new RequestData($request))->post('password', ''),
        ];
        if ($initial) {
            $validation = $this->validateRootCredentials($input);
            if (!$validation->isValid()) {
                return $this->show($request, [
                    'errors' => $validation->errors(),
                    'email' => $input['email'],
                ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
            }
            $input['email'] = (string) $validation->validated()['email'];
            $input['password'] = (string) $validation->validated()['password'];
        }

        $result = $this->localizeResult($this->installation->run($initial, $initial ? $input['email'] : null, $initial ? $input['password'] : null));

        return $this->show(
            $request,
            ['result' => $result, 'email' => $initial ? $input['email'] : ''],
            $result['success'] ? HttpStatusCode::OK->value : HttpStatusCode::INTERNAL_SERVER_ERROR->value,
        );
    }

    /**
     * Zpracovava krok runjson v instalaci aplikace
     */
    private function runJson(bool $initial, ServerRequestInterface $request): ResponseInterface
    {
        $step = InstallStep::tryFrom((string) (new RequestData($request))->input('step', ''));
        if ($step === null || $step === InstallStep::Discovery) {
            return $this->jsonError($step, 'install.installErrors.step_invalid', HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }

        $email = null;
        $password = null;
        if ($step === InstallStep::Root && $initial) {
            $validation = $this->validateRootCredentials([
                'email' => (string) (new RequestData($request))->input('email', ''),
                'password' => (string) (new RequestData($request))->input('password', ''),
            ]);
            if (!$validation->isValid()) {
                $message = (string) (array_values($validation->errors())[0] ?? $this->translator->get('install.install.result.failure'));

                return $this->jsonStep(
                    $step,
                    InstallStepStatus::Error,
                    $message,
                    null,
                    ['errors' => $validation->errors()],
                    HttpStatusCode::UNPROCESSABLE_ENTITY->value,
                );
            }
            $email = (string) $validation->validated()['email'];
            $password = (string) $validation->validated()['password'];
        }

        $result = $this->installation->executeStep($step, $initial, $email, $password);
        if ($result['status'] === InstallStepStatus::Success && (($initial && $step === InstallStep::Root) || (!$initial && $step === InstallStep::Permissions))) {
            $this->installation->clearInstallState();
        }

        return $this->jsonStep(
            $result['step'],
            $result['status'],
            $this->localizeInstallerMessage($result['messageKey']),
            $result['nextStep'],
            ['rootCreated' => $result['rootCreated']],
            $result['status'] === InstallStepStatus::Error
                ? HttpStatusCode::INTERNAL_SERVER_ERROR->value
                : HttpStatusCode::OK->value,
        );
    }

    /**
     * Zpracovava krok jsonstep v instalaci aplikace
     * @param array<string,mixed> $extra
     */
    private function jsonStep(?InstallStep $step, InstallStepStatus $status, string $message, ?InstallStep $nextStep, array $extra = [], ?int $httpStatus = null): ResponseInterface
    {
        $httpStatus ??= HttpStatusCode::OK->value;
        return $this->responses->json([
            'success' => $status !== InstallStepStatus::Error,
            'step' => $step?->value,
            'status' => $status->value,
            'message' => $message,
            'nextStep' => $nextStep?->value,
            'csrfToken' => $this->csrf->token(),
            ...$extra,
        ], $httpStatus);
    }

    /**
     * Zpracovava krok jsonerror v instalaci aplikace
     */
    private function jsonError(?InstallStep $step, string $messageKey, int $status): ResponseInterface
    {
        return $this->jsonStep($step, InstallStepStatus::Error, $this->translator->get($messageKey), null, [], $status);
    }

    /**
     * Zpracovava krok authorizeinitialrequest v instalaci aplikace
     */
    private function authorizeInitialRequest(ServerRequestInterface $request): bool
    {
        $provided = (string) (new RequestData($request))->input('install_key', '');
        if ($provided !== '') {
            if (!$this->validInstallKey($provided)) {
                return false;
            }
            $this->installation->authorizeInstall();

            return true;
        }

        return $this->installation->installIsAuthorized();
    }

    /**
     * Rozhoduje, zda plati podminka validinstallkey
     */
    private function validInstallKey(string $provided): bool
    {
        $expected = getenv('INSTALL_KEY');

        return is_string($expected) && $expected !== '' && hash_equals($expected, $provided);
    }

    /**
     * Rozhoduje, zda plati podminka validaterootcredentials
     * @param array<string,mixed> $input
     */
    private function validateRootCredentials(array $input): ValidationResult
    {
        return $this->validator->validate($input, ValidationSchema::create()
            ->field('email', $this->translator->get('install.install.fields.email'))
                ->required($this->translator->get('install.install.validation.email_required'))
                ->email($this->translator->get('install.install.validation.email_invalid'))
                ->maxLength(254, $this->translator->get('install.install.validation.email_invalid'))
            ->field('password', $this->translator->get('install.install.fields.password'))
                ->required($this->translator->get('install.install.validation.password_required'))
                ->minLength(12, $this->translator->get('install.install.validation.password_min_length'))
            ->end());
    }

    /**
     * Zpracovava krok installertranslations v instalaci aplikace
     * @return array<string,array<string,mixed>>
     */
    private function installerTranslations(): array
    {
        return [
            'cs' => $this->installerLocale('cs'),
            'en' => $this->installerLocale('en'),
        ];
    }

    /**
     * Zpracovava krok installerlocale v instalaci aplikace
     * @return array<string,array<string,mixed>>
     */
    private function installerLocale(string $locale): array
    {
        $translate = fn(string $key): string => $this->translator->get($key, [], $locale);

        return [
            'install' => [
                'brand' => [
                    'title' => $translate('install.brand.title'),
                    'subtitle' => $translate('install.brand.subtitle'),
                    'poweredByLemonade' => $translate('install.brand.poweredByLemonade'),
                ],
                'theme' => [
                    'switchToLight' => $translate('install.theme.switchToLight'),
                    'switchToDark' => $translate('install.theme.switchToDark'),
                ],
                'locale' => [
                    'label' => $translate('install.locale.label'),
                    'czech' => $translate('install.locale.czech'),
                    'english' => $translate('install.locale.english'),
                ],
                'password' => [
                    'show' => $translate('install.password.show'),
                    'hide' => $translate('install.password.hide'),
                ],
                'install' => [
                    'title' => $translate('install.install.title'),
                    'update_title' => $translate('install.install.update_title'),
                    'description' => $translate('install.install.description'),
                    'update_description' => $translate('install.install.update_description'),
                    'fields' => [
                        'install_key' => $translate('install.install.fields.install_key'),
                        'email' => $translate('install.install.fields.email'),
                        'password' => $translate('install.install.fields.password'),
                    ],
                    'validation' => [
                        'email_required' => $translate('install.install.validation.email_required'),
                        'email_invalid' => $translate('install.install.validation.email_invalid'),
                        'password_required' => $translate('install.install.validation.password_required'),
                        'password_min_length' => $translate('install.install.validation.password_min_length'),
                    ],
                    'errors' => [
                        'invalid_key' => $translate('install.install.errors.invalid_key'),
                    ],
                    'preflight' => [
                        'title' => $translate('install.install.preflight.title'),
                    ],
                    'checks' => [
                        'php' => $translate('install.install.checks.php'),
                        'extension' => [
                            'fileinfo' => $translate('install.install.checks.extension.fileinfo'),
                            'mbstring' => $translate('install.install.checks.extension.mbstring'),
                            'pdo' => $translate('install.install.checks.extension.pdo'),
                            'pdo_mysql' => $translate('install.install.checks.extension.pdo_mysql'),
                        ],
                        'database' => [
                            'configuration' => $translate('install.install.checks.database.configuration'),
                            'connection' => $translate('install.install.checks.database.connection'),
                        ],
                        'storage' => $translate('install.install.checks.storage'),
                        'cache' => $translate('install.install.checks.cache'),
                        'vendor' => $translate('install.install.checks.vendor'),
                        'assets' => [
                            'admin_css' => $translate('install.install.checks.assets.admin_css'),
                            'admin_js' => $translate('install.install.checks.assets.admin_js'),
                        ],
                    ],
                    'status' => [
                        'success' => $translate('install.install.status.success'),
                        'error' => $translate('install.install.status.error'),
                        'skipped' => $translate('install.install.status.skipped'),
                    ],
                    'steps' => [
                        'title' => $translate('install.install.steps.title'),
                        'environment' => $translate('install.installSteps.environment'),
                        'discovery' => $translate('install.install.steps.discovery'),
                        'database' => $translate('install.install.steps.database'),
                        'modules' => $translate('install.install.steps.modules'),
                        'permissions' => $translate('install.install.steps.permissions'),
                        'root' => $translate('install.install.steps.root'),
                    ],
                    'result' => [
                        'success' => $translate('install.install.result.success'),
                        'failure' => $translate('install.install.result.failure'),
                    ],
                    'actions' => [
                        'install' => $translate('install.install.actions.install'),
                        'update' => $translate('install.install.actions.update'),
                        'retry' => $translate('install.install.actions.retry'),
                    ],
                ],
                'installErrors' => [
                    'php' => $translate('install.installErrors.php'),
                    'extension' => [
                        'fileinfo' => $translate('install.installErrors.extension.fileinfo'),
                        'mbstring' => $translate('install.installErrors.extension.mbstring'),
                        'pdo' => $translate('install.installErrors.extension.pdo'),
                        'pdo_mysql' => $translate('install.installErrors.extension.pdo_mysql'),
                    ],
                    'database' => $translate('install.installErrors.database'),
                    'database_host' => $translate('install.installErrors.database_host'),
                    'database_port' => $translate('install.installErrors.database_port'),
                    'database_name' => $translate('install.installErrors.database_name'),
                    'database_user' => $translate('install.installErrors.database_user'),
                    'database_skipped' => $translate('install.installErrors.database_skipped'),
                    'storage' => $translate('install.installErrors.storage'),
                    'cache' => $translate('install.installErrors.cache'),
                    'vendor' => $translate('install.installErrors.vendor'),
                    'assets' => [
                        'admin_css' => $translate('install.installErrors.assets.admin_css'),
                        'admin_js' => $translate('install.installErrors.assets.admin_js'),
                    ],
                    'preflight' => $translate('install.installErrors.preflight'),
                    'credentials' => $translate('install.installErrors.credentials'),
                    'catalog' => $translate('install.installErrors.catalog'),
                    'step_order' => $translate('install.installErrors.step_order'),
                    'step_invalid' => $translate('install.installErrors.step_invalid'),
                    'operation_failed' => $translate('install.installErrors.operation_failed'),
                ],
                'installSteps' => [
                    'environment' => $translate('install.installSteps.environment'),
                    'pending' => $translate('install.installSteps.pending'),
                    'running' => $translate('install.installSteps.running'),
                    'success' => $translate('install.installSteps.success'),
                    'error' => $translate('install.installSteps.error'),
                    'skipped' => $translate('install.installSteps.skipped'),
                    'prepare' => $translate('install.installSteps.prepare'),
                    'refresh_catalog' => $translate('install.installSteps.refresh_catalog'),
                ],
                'installPresentation' => [
                    'preflight_summary' => $translate('install.installPresentation.preflight_summary'),
                    'progress' => $translate('install.installPresentation.progress'),
                    'progress_completed' => $translate('install.installPresentation.progress_completed'),
                    'help' => [
                        'install_key' => $translate('install.installPresentation.help.install_key'),
                        'email' => $translate('install.installPresentation.help.email'),
                        'password' => $translate('install.installPresentation.help.password'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Rozhoduje, zda plati podminka isrootuser
     */
    private function isRootUser(): bool
    {
        $principal = $this->currentUser->currentPrincipal();

        return $principal instanceof LocalAdminPrincipal
            && $this->authorization->isRoot($principal->user());
    }

    /**
     * Zpracovava krok notfound v instalaci aplikace
     */
    private function notFound(): ResponseInterface
    {
        return $this->responses->text('404 Not Found', HttpStatusCode::NOT_FOUND->value);
    }

    /**
     * Zpracovava krok localizeresult v instalaci aplikace
     * @param array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool} $result
     * @return array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool}
     */
    private function localizeResult(array $result): array
    {
        foreach ($result['steps'] as &$step) {
            $step['error'] = $this->localizeInstallerMessage($step['error']);
        }
        unset($step);

        return $result;
    }

    /**
     * Zpracovava krok localizepreflight v instalaci aplikace
     * @param array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>} $preflight
     * @return array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>}
     */
    private function localizePreflight(array $preflight): array
    {
        foreach ($preflight['checks'] as &$check) {
            $check['detail'] = $this->localizeInstallerMessage($check['detail']);
        }
        unset($check);

        return $preflight;
    }

    /**
     * Zpracovava krok localizeinstallermessage v instalaci aplikace
     */
    private function localizeInstallerMessage(string $message): string
    {
        if (str_starts_with($message, 'install.install.') || str_starts_with($message, 'install.installSteps.') || str_starts_with($message, 'install.installErrors.') || in_array($message, [
            'users.validation.email_taken',
            'users.validation.email_invalid',
            'users.validation.password_min_length',
        ], true)) {
            return $this->translator->get($message);
        }

        return $message;
    }

    /**
     * Zpracovava krok safeerror v instalaci aplikace
     */
    private function safeError(\Throwable $exception): string
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

        return preg_match('/(?:mysql:|pgsql:|sqlite:|dsn\s*[=:]|sqlstate\[|database connection failed)/i', $message) === 1
            ? 'install.installErrors.database'
            : (preg_replace('/(password|passwd|pwd|secret|token)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? 'install.installErrors.operation_failed');
    }
}
