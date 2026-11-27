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
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Admin\System\Users\Validation\UserPasswordPolicy;
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
 * Handles the application installation HTTP workflow
 */
final readonly class InstallController
{
    /**
     * Creates the controller for installation and upgrade requests
     */
    public function __construct(
        private InstallationService $installation,
        private AdminUiLocale $uiLocale,
        private CurrentUserProvider $currentUser,
        private AuthorizationService $authorization,
        private CsrfTokenManager $csrf,
        private AuthPageRenderer $pages,
        private AdminPageRenderer $adminPages,
        private Responses $responses,
        private HttpRequestInspector $requests,
        private TranslatorInterface $translator,
        private FormValidation $validator,
        private ClientTranslationVersion $translationVersion,
        private Router $router,
    ) {}

    /**
     * Displays the initial installation page
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->installed()) {
            return $this->notFound();
        }

        return $this->show($request, true);
    }

    /**
     * Displays the installed application upgrade workflow to a root user
     */
    public function upgrade(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->installed() || !$this->isRootUser()) {
            return $this->notFound();
        }

        return $this->show($request, false);
    }

    /**
     * Runs the requested installation step
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->installed()) {
            return $this->notFound();
        }

        return $this->runWorkflow($request, true);
    }

    /**
     * Runs an installed application upgrade authorized by a root user
     */
    public function runUpgrade(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->installed() || !$this->isRootUser()) {
            return $this->notFound();
        }

        return $this->runWorkflow($request, false);
    }

    /**
     * Executes the authorized installation or upgrade workflow
     */
    private function runWorkflow(ServerRequestInterface $request, bool $initial): ResponseInterface
    {
        $preflight = $this->installation->preflight();

        if (!$preflight['ready']) {
            return $this->preflightFailureResponse($request, $initial);
        }

        if ($initial && !$this->authorizeInitialRequest($request)) {
            return $this->requests->wantsJson($request)
                ? $this->jsonError(null, 'install.install.errors.invalid_key', HttpStatusCode::FORBIDDEN->value)
                : $this->show($request, true, ['generalError' => $this->translator->get('install.install.errors.invalid_key')], HttpStatusCode::FORBIDDEN->value);
        }

        return $this->requests->wantsJson($request) ? $this->runJson($initial, $request) : $this->runHtml($initial, $request);
    }

    /**
     * Renders the installation workflow with optional response data
     *
     * @param array<string, mixed> $extra
     */
    private function show(ServerRequestInterface $request, bool $initial, array $extra = [], ?int $status = null): ResponseInterface
    {
        $status ??= HttpStatusCode::OK->value;
        $state = $this->installation->state();
        $requestData = new RequestData($request);
        $locale = $this->uiLocale->activate(
            (string) $requestData->query('locale', ''),
            (string) $requestData->cookie('lemonade_locale', ''),
        );

        $data = [
            'title' => $this->translator->get($initial ? 'install.install.title' : 'install.install.update_title'),
            'locale' => $locale,
            'installer' => true,
            'clientTranslationVersion' => $this->translationVersion,
            'installerTranslations' => $this->installerTranslations(),
            'initial' => $initial,
            'workflowEndpoint' => $this->router->url($initial ? 'admin.install.run' : 'admin.upgrade.run'),
            'state' => $state,
            'preflight' => $this->localizePreflight($this->installation->preflight()),
            'stepStates' => $this->installation->stepStates($initial),
            'errors' => $extra['errors'] ?? [],
            'generalError' => $extra['generalError'] ?? null,
            'result' => $extra['result'] ?? null,
            'email' => $extra['email'] ?? '',
        ];

        if (!$initial) {
            return $this->adminPages->render('admin-install::upgrade', [
                ...$data,
                'title' => $this->translator->get('admin.account.system_upgrade'),
                'workflowCompletedUrl' => $this->router->url('admin.dashboard'),
            ], $status);
        }

        return $this->pages->render('admin-install::install', $data, $status);
    }

    /**
     * Returns the preflight failure response for the requested workflow
     */
    private function preflightFailureResponse(ServerRequestInterface $request, bool $initial): ResponseInterface
    {
        $message = $this->translator->get('install.installErrors.preflight');

        return $this->requests->wantsJson($request)
            ? $this->jsonError(null, 'install.installErrors.preflight', HttpStatusCode::UNPROCESSABLE_ENTITY->value)
            : $this->show($request, $initial, ['generalError' => $message], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
    }

    /**
     * Runs an installation step submitted through the HTML workflow
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
                return $this->show($request, true, [
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
            $initial,
            ['result' => $result, 'email' => $initial ? $input['email'] : ''],
            $result['success'] ? HttpStatusCode::OK->value : HttpStatusCode::INTERNAL_SERVER_ERROR->value,
        );
    }

    /**
     * Runs an installation step submitted through the JSON workflow
     */
    private function runJson(bool $initial, ServerRequestInterface $request): ResponseInterface
    {
        $step = InstallStep::tryFrom((string) (new RequestData($request))->input('step', ''));

        if ($step === null) {
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
     * Creates a JSON response for an installation step
     *
     * @param array<string, mixed> $extra
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
     * Creates a localized JSON error response
     */
    private function jsonError(?InstallStep $step, string $messageKey, int $status): ResponseInterface
    {
        return $this->jsonStep($step, InstallStepStatus::Error, $this->translator->get($messageKey), null, [], $status);
    }

    /**
     * Authorizes an initial installation request
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
     * Verifies the supplied installation key
     */
    private function validInstallKey(string $provided): bool
    {
        $expected = getenv('INSTALL_KEY');

        return is_string($expected) && $expected !== '' && hash_equals($expected, $provided);
    }

    /**
     * Validates the initial root account credentials
     *
     * @param array<string, mixed> $input
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
                ->minLength(UserPasswordPolicy::MIN_LENGTH, $this->translator->get('install.install.validation.password_min_length'))
            ->end());
    }

    /**
     * Returns installer translations for all supported locales
     *
     * @return array<string, array<string, mixed>>
     */
    private function installerTranslations(): array
    {
        return [
            'cs' => $this->installerLocale('cs'),
            'en' => $this->installerLocale('en'),
        ];
    }

    /**
     * Returns the installer translations for one locale
     *
     * @return array<string, array<string, mixed>>
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
     * Reports whether the current user has root authority
     */
    private function isRootUser(): bool
    {
        $principal = $this->currentUser->currentPrincipal();

        return $principal instanceof LocalAdminPrincipal
            && $this->authorization->isRoot($principal->user());
    }

    /**
     * Reports whether the application and canonical root account are installed
     */
    private function installed(): bool
    {
        return $this->installation->state()['rootAccount'];
    }

    /**
     * Creates the installation not-found response
     */
    private function notFound(): ResponseInterface
    {
        return $this->responses->text('404 Not Found', HttpStatusCode::NOT_FOUND->value);
    }

    /**
     * Localizes an installation execution result
     *
     * @param array{success: bool, steps: list<array{key: string, ok: bool, error: string}>, rootCreated: bool} $result
     * @return array{success: bool, steps: list<array{key: string, ok: bool, error: string}>, rootCreated: bool}
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
     * Localizes an installation preflight result
     *
     * @param array{ready: bool, checks: list<array{key: string, ok: bool, status: string, detail: string}>} $preflight
     * @return array{ready: bool, checks: list<array{key: string, ok: bool, status: string, detail: string}>}
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
     * Localizes a known installer message key
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
}
