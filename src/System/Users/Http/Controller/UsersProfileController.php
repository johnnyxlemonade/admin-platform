<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Http\Controller;

use Lemonade\Admin\Action\Http\Controller\ModuleActionController;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorRecordConflictException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Editor\Lock\EditorLockException;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Admin\System\Audit\Models\PersonalAccountActivityReadModel;
use Lemonade\Admin\System\Users\Exceptions\UserNotFoundException;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Services\SelfPasswordChangeService;
use Lemonade\Admin\System\Users\UsersModulePageProvider;
use Lemonade\Admin\System\Users\Validation\UserPasswordValidationSchema;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenNames;
use Lemonade\Framework\Validation\FormValidation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles the canonical self-service profile without accepting a target user ID
 */
final readonly class UsersProfileController
{
    /**
     * Creates the profile controller with editor and account capabilities
     */
    public function __construct(
        private CurrentPrincipalProviderInterface $currentUser,
        private EditorDispatcher $editors,
        private EditorLockManager $locks,
        private UsersModulePageProvider $pages,
        private PersonalAccountActivityReadModel $activity,
        private SelfPasswordChangeService $passwords,
        private UserPasswordValidationSchema $passwordValidation,
        private FormValidation $validator,
        private TotpCredentialService $mfaCredentials,
        private ModuleActionController $actions,
        private ModuleController $modules,
        private AdminUiLocale $locale,
        private TranslatorInterface $translator,
        private AdminResponseFactory $responses,
        private AdminPageRenderer $renderer,
        private UrlGenerator $urls,
    ) {}

    /**
     * Displays the current user's personal information section
     */
    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        return $this->profilePage($request, 'basic');
    }

    /**
     * Displays the current user's security section
     */
    public function security(ServerRequestInterface $request): ResponseInterface
    {
        return $this->profilePage($request, 'security');
    }

    /**
     * Displays the current browser preference section
     */
    public function preferences(ServerRequestInterface $request): ResponseInterface
    {
        return $this->profilePage($request, 'preferences');
    }

    /**
     * Displays recent supported activity for the current account
     */
    public function activity(ServerRequestInterface $request): ResponseInterface
    {
        return $this->profilePage($request, 'activity');
    }

    /**
     * Saves the current user's editable personal information
     */
    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        try {
            $outcome = $this->editors->update('system.users', $userId, $this->editorPayload($request));
        } catch (EditorValidationException $exception) {
            return $this->profilePage(
                request: $request,
                section: 'basic',
                errors: ['_form' => $this->translator->get($exception->messageKey())],
                input: $this->safeInput($request),
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        } catch (EditorEntityNotFoundException) {
            return $this->responses->notFound($request);
        } catch (EditorLockException) {
            return $this->responses->recordLocked($request, $this->profileUrl());
        } catch (EditorRecordConflictException) {
            return $this->responses->recordConflict($request, $this->profileUrl());
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatus::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }

        if (!$outcome->isSaved()) {
            return $this->profilePage(
                request: $request,
                section: 'basic',
                errors: $outcome->validation()->errors(),
                input: $outcome->input(),
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $this->locks->release('system.users', (string) $userId);

        return $this->responses->redirect($this->profileUrl());
    }

    /**
     * Changes the current local user's password after framework validation
     */
    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        $input = (new RequestData($request))->postAll();
        $validation = $this->validator->validate($input, $this->passwordValidation->changeOwnPassword());

        if (!$validation->isValid()) {
            return $this->profilePage($request, 'security', passwordErrors: $validation->errors(), status: HttpStatus::UNPROCESSABLE_ENTITY);
        }

        $validated = $validation->validated();

        try {
            $this->passwords->change(
                $userId,
                (string) $validated['current_password'],
                (string) $validated['new_password'],
            );
        } catch (UserSafetyException $exception) {
            return $this->profilePage(
                $request,
                'security',
                passwordErrors: ['_form' => $this->translator->get($exception->translationKey())],
                status: HttpStatus::UNPROCESSABLE_ENTITY,
            );
        } catch (LocalActorRequiredException) {
            return $this->responses->authorizationDenied($request);
        } catch (UserNotFoundException) {
            return $this->responses->notFound($request);
        }

        return $this->profilePage($request, 'security', passwordChanged: true);
    }

    /**
     * Delegates self-profile file actions to the shared action transport
     */
    public function ajaxSave(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        return $this->actions->entity('users', $userId, $request);
    }

    /**
     * Redirects self-editing to the profile and delegates other users to management
     */
    public function edit(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $currentUserId = $this->currentUserId();

        if ($currentUserId !== null && $id === $currentUserId) {
            return $this->responses->redirect($this->profileUrl());
        }

        return $this->modules->edit('users', $id, $request);
    }

    /**
     * Loads and displays one self-service profile section
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param array<string, string> $passwordErrors
     */
    private function profilePage(
        ServerRequestInterface $request,
        string $section,
        array $errors = [],
        array $input = [],
        array $passwordErrors = [],
        bool $passwordChanged = false,
        HttpStatus $status = HttpStatus::OK,
    ): ResponseInterface {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->responses->authorizationDenied($request);
        }

        $locale = $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));

        try {
            $editor = $this->editors->updateData('system.users', $userId);
        } catch (EditorEntityNotFoundException) {
            return $this->responses->notFound($request);
        } catch (EditorCapabilityException $exception) {
            return $exception->statusCode() === HttpStatus::FORBIDDEN
                ? $this->responses->authorizationDenied($request)
                : $this->responses->notFound($request);
        }

        if ($section === 'basic') {
            $this->locks->acquire('system.users', (string) $userId);
        }

        return $this->renderProfile(
            editor: $editor,
            section: $section,
            errors: $errors,
            input: $input,
            passwordErrors: $passwordErrors,
            passwordChanged: $passwordChanged,
            locale: $locale,
            status: $status,
        );
    }

    /**
     * Renders the profile with section-specific account data
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param array<string, string> $passwordErrors
     */
    private function renderProfile(
        EditorLoaded $editor,
        string $section,
        array $errors,
        array $input,
        array $passwordErrors,
        bool $passwordChanged,
        string $locale,
        HttpStatus $status,
    ): ResponseInterface {
        $userId = (int) $editor->data()['user']['id'];
        $page = $this->pages->profile(
            editor: $editor,
            errors: $errors,
            input: $input,
            locale: $locale,
            section: $section,
            activity: $section === 'activity' ? $this->activity->recent($userId) : [],
            profileUpdatedAt: $this->activity->latestProfileUpdateAt($userId),
            passwordChangedAt: $this->activity->latestPasswordChangeAt($userId),
            mfaEnabledAt: $this->mfaCredentials->enabledAt($userId),
            passwordErrors: $passwordErrors,
            passwordChanged: $passwordChanged,
        );

        return $this->renderer->render($page->view(), ['title' => $page->title(), ...$page->data()], $status->value);
    }

    /**
     * Returns the current authenticated user identifier
     */
    private function currentUserId(): ?int
    {
        return $this->currentUser->currentUser()?->id();
    }

    /**
     * Returns safe personal input without a password value
     *
     * @return array<string, mixed>
     */
    private function safeInput(ServerRequestInterface $request): array
    {
        $input = $this->editorPayload($request);
        unset($input['local_password']);

        return $input;
    }

    /**
     * Extracts the shared editor payload from a profile request
     *
     * @return array<string, mixed>
     */
    private function editorPayload(ServerRequestInterface $request): array
    {
        $payload = (new RequestData($request))->postAll();
        unset($payload[CsrfTokenNames::FORM_FIELD]);

        $editorPayload = $payload['payload'] ?? $payload;

        return is_array($editorPayload) ? $editorPayload : [];
    }

    /**
     * Returns the canonical self-profile URL
     */
    private function profileUrl(): string
    {
        return $this->urls->route('admin.system.users.profile');
    }
}
