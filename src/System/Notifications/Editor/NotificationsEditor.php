<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Editor;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationPublicationService;
use Lemonade\Admin\Notification\NotificationType;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Propojuje management editor s canonical publikovanim a upravou oznameni
 */
final class NotificationsEditor implements EditorProviderInterface
{
    /**
     * Nastavuje publisher, local-actor guard a cteni management zaznamu
     */
    public function __construct(
        private readonly NotificationPublicationService $publisher,
        private readonly LocalActorGuard $actors,
        private readonly NotificationModel $notifications,
    ) {}

    public function editorDefinition(): EditorDefinition
    {
        return new EditorDefinition(
            loadPermission: 'system.notifications.publish',
            savePermission: 'system.notifications.publish',
            createPermission: 'system.notifications.publish',
            fields: [
                new EditorFieldDefinition(name: 'type', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'title', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'message', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'audience_type', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'role_ids', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'user_ids', type: 'text', readOnly: false, permission: null),
            ],
        );
    }

    public function updateData(int $id): array
    {
        $notification = $this->notifications->notificationForEditor($id);
        if ($notification === null || $notification['deleted_at'] !== null) {
            throw new EditorEntityNotFoundException('Notification not found.');
        }

        return ['notification' => [
            ...$notification,
            'audience_type' => $notification['role_ids'] !== [] ? 'roles' : 'users',
        ]];
    }

    public function createData(): array
    {
        return [
            'notification' => [
                'type' => 'info',
                'title' => '',
                'message' => '',
                'audience_type' => 'roles',
                'role_ids' => [],
                'user_ids' => [],
            ],
        ];
    }

    public function updateValidationSchema(int $id): ValidationSchema
    {
        return $this->schema();
    }

    public function createValidationSchema(): ValidationSchema
    {
        return $this->schema();
    }

    /**
     * Definuje vstupni omezeni obsahu a vyberu publika
     */
    private function schema(): ValidationSchema
    {
        return ValidationSchema::create()
            ->field('type', 'Type')
                ->required('notifications.validation.type_required')
                ->inList(array_column(NotificationType::cases(), 'value'), 'notifications.validation.type_invalid')
            ->field('title', 'Title')
                ->required('notifications.validation.title_required')
                ->maxLength(180, 'notifications.validation.title_long')
            ->field('message', 'Message')
                ->required('notifications.validation.message_required')
                ->maxLength(1000, 'notifications.validation.message_long')
            ->field('audience_type', 'Audience type')
                ->required('notifications.validation.audience_type_required')
                ->inList(['roles', 'users'], 'notifications.validation.audience_type_invalid')
            ->field('role_ids', 'Roles')
            ->field('user_ids', 'Users')
            ->end();
    }

    public function update(int $id, array $data): EditorSaveResult
    {
        try {
            $actor = $this->actor();
            $this->publisher->update(
                actor: $actor,
                id: $id,
                type: NotificationType::from((string) $data['type']),
                title: trim((string) $data['title']),
                message: trim((string) $data['message']),
                roleIds: $this->ids($data, 'role_ids'),
                userIds: $this->ids($data, 'user_ids'),
            );

            return new EditorSaveResult(data: ['id' => $id], messageKey: 'notifications.editor.updated');
        } catch (\ValueError) {
            throw new EditorValidationException('notifications.validation.type_invalid');
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            throw new EditorValidationException($exception->getMessage(), previous: $exception);
        }
    }

    public function create(array $data): EditorSaveResult
    {
        try {
            $actor = $this->actor();
            $id = $this->publisher->publish(
                actor: $actor,
                type: NotificationType::from((string) $data['type']),
                title: trim((string) $data['title']),
                message: trim((string) $data['message']),
                roleIds: $this->ids($data, 'role_ids'),
                userIds: $this->ids($data, 'user_ids'),
            );

            return new EditorSaveResult(data: ['id' => $id], messageKey: 'notifications.editor.published');
        } catch (\ValueError) {
            throw new EditorValidationException('notifications.validation.type_invalid');
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            throw new EditorValidationException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Zpristupnuje pole identifikatoru publika pro canonical publisher
     *
     * @param array<string, mixed> $data
     * @return array<int, int|string>
     */
    private function ids(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    /**
     * Vyzaduje lokalniho uzivatele pro auditovanou management mutaci
     */
    private function actor(): AuthenticatedUser
    {
        return $this->actors->requireLocalUser();
    }
}
