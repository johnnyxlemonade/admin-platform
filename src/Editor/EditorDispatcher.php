<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Editor\Contract\EditorAccessPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorLockBypassPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Lock\EditorLockException;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Validation\FormValidation;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final class EditorDispatcher
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleManager $moduleManager,
        private readonly EditorRegistry $editors,
        private readonly AuthorizationService $authorization,
        private readonly FormValidation $validation,
        private readonly EditorLockManager $locks,
    ) {}

    /**
     * Nacte data existujiciho zaznamu pro jeho upravu
     */
    public function updateData(string $module, int $id): EditorLoaded
    {
        [$provider, $definition] = $this->provider($module, false, false, $id);

        return new EditorLoaded($definition, $provider->updateData($id));
    }

    /**
     * Vrati vychozi data pro zalozeni zaznamu v editoru
     */
    public function createData(string $module): EditorLoaded
    {
        [$provider, $definition] = $this->provider($module, false, true);

        return new EditorLoaded($definition, $provider->createData());
    }

    /**
     * Validuje povolena data a upravi existujici zaznam
     *
     * @param array<string, mixed> $payload
     */
    public function update(string $module, int $id, array $payload): EditorSaveOutcome
    {
        [$provider, $definition] = $this->provider($module, true, false, $id);
        try {
            $this->locks->assertOwned($module, (string) $id);
        } catch (EditorLockException $exception) {
            if (!$this->canBypassSaveWithoutLock($provider, $id, $payload)) {
                throw $exception;
            }
        }
        $input = $this->allowedInput($definition, $payload);
        $validation = $this->validation->validate($input, $provider->updateValidationSchema($id));

        if (!$validation->isValid()) {
            return EditorSaveOutcome::invalid($validation, $this->safeInput($definition, $input));
        }

        return EditorSaveOutcome::saved($provider->update($id, $validation->validated()));
    }

    /**
     * Rozhoduje stav canbypassforeignlock
     */
    public function canBypassForeignLock(string $module, int $id): bool
    {
        [$provider] = $this->provider($module, false, false, $id);

        return $provider instanceof EditorLockBypassPolicyInterface && $provider->canBypassForeignLock($id);
    }

    /**
     * Vytvari novou instanci s predanym nastavenim
     * @param array<string, mixed> $payload
     */
    public function create(string $module, array $payload): EditorSaveOutcome
    {
        [$provider, $definition] = $this->provider($module, true, true);
        $input = $this->allowedInput($definition, $payload);
        $validation = $this->validation->validate($input, $provider->createValidationSchema());

        if (!$validation->isValid()) {
            return EditorSaveOutcome::invalid($validation, $this->safeInput($definition, $input));
        }

        return EditorSaveOutcome::saved($provider->create($validation->validated()));
    }

    /**
     * Vraci registrovany provider pro kod modulu
     * @return array{0:EditorProviderInterface,1:EditorDefinition}
     */
    private function provider(string $module, bool $saving, bool $creating = false, ?int $id = null): array
    {
        if (!$this->modules->has($module)) {
            throw new EditorCapabilityException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Module not found.',
            );
        }

        if (!$this->moduleManager->enabled($module)) {
            throw new EditorCapabilityException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Module is not available.',
            );
        }

        if (!$this->editors->has($module)) {
            throw new EditorCapabilityException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::UNSUPPORTED_ACTION,
                'Module does not provide an editor.',
            );
        }

        $provider = $this->editors->provider($module);
        $definition = $provider->editorDefinition();
        $permission = $creating
            ? $definition->createPermission()
            : ($saving ? $definition->savePermission() : $definition->loadPermission());
        $decision = $id !== null && $provider instanceof EditorAccessPolicyInterface
            ? ($saving ? $provider->saveAccessDecision($id) : $provider->loadAccessDecision($id))
            : null;
        if ($decision !== true && ($decision === false || !$this->authorization->hasPermission($permission))) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::PERMISSION_DENIED,
                'Permission denied.',
            );
        }

        return [$provider, $definition];
    }

    /**
     * Rozhoduje stav canbypasssavewithoutlock
     * @param array<string, mixed> $payload
     */
    private function canBypassSaveWithoutLock(EditorProviderInterface $provider, int $id, array $payload): bool
    {
        return $provider instanceof EditorLockBypassPolicyInterface && $provider->canSaveWithoutLock($id, $payload);
    }

    /**
     * Zpracovava hodnotu allowedinput v konfiguraci editoru
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function allowedInput(EditorDefinition $definition, array $payload): array
    {
        $input = [];
        foreach ($definition->fields() as $field) {
            if (!array_key_exists($field->name(), $payload)) {
                continue;
            }

            if ($field->readOnly()) {
                continue;
            }

            $permission = $field->permission();
            if ($permission !== null && !$this->authorization->hasPermission($permission)) {
                throw new EditorCapabilityException(
                    HttpStatusCode::FORBIDDEN,
                    AdminErrorCode::PERMISSION_DENIED,
                    'Permission denied.',
                );
            }

            $input[$field->name()] = $payload[$field->name()];
        }

        return $input;
    }

    /**
     * Zpracovava hodnotu safeinput v konfiguraci editoru
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function safeInput(EditorDefinition $definition, array $input): array
    {
        foreach ($definition->fields() as $field) {
            if ($field->type() === 'password') {
                unset($input[$field->name()]);
            }
        }

        return $input;
    }
}
