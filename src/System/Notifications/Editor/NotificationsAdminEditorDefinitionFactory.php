<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Editor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\Notification\NotificationType;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada modalni AdminEditor pro management obsahu a publika oznameni
 */
final class NotificationsAdminEditorDefinitionFactory
{
    /**
     * Nastavuje generator URL shared modalniho transportu
     */
    public function __construct(private readonly UrlGenerator $urls) {}

    /**
     * Sklada create nebo edit formular s obsahem a explicitnim publikem
     *
     * @param array<string, mixed> $notification
     * @param list<array{id:int,code:string,name:string,is_super_admin:int}> $roles
     * @param array<string, mixed> $input
     */
    public function modal(array $notification, array $roles, array $input, bool $editing): AdminEditorDefinition
    {
        $id = (int) ($notification['id'] ?? 0);
        $formAction = $editing
            ? $this->urls->route(name: 'admin.system.module.edit', params: ['module' => 'notifications', 'id' => $id])
            : $this->urls->route(name: 'admin.system.module.create', params: ['module' => 'notifications']);
        $actionUrl = $editing
            ? $this->urls->route(name: 'admin.system.module.ajax.entity', params: ['module' => 'notifications', 'id' => $id])
            : $this->urls->route(name: 'admin.system.module.ajax.create', params: ['module' => 'notifications']);

        return AdminEditorBuilder::create('system.notifications.modal')
            ->form(new AdminEditorFormDefinition(
                id: 'notifications-modal-editor-form',
                action: $formAction,
                actionUrl: $actionUrl,
                actionKey: $editing ? 'save' : 'create',
                novalidate: true,
            ))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    field: AdminEditorFieldDefinition::select(name: 'type', labelKey: 'notifications.fields.type')
                            ->options($this->typeOptions())
                            ->optionLabelKeys($this->typeLabelKeys())
                            ->attributes(['data-lemonade-option-source' => 'static'])
                            ->required(),
                    md: 4,
                ),
                new FieldColumn(
                    field: AdminEditorFieldDefinition::text(name: 'title', labelKey: 'notifications.fields.title')
                            ->attributes(['maxlength' => '180'])
                            ->required(),
                    md: 8,
                ),
            ]))
            ->block(
                AdminEditorFieldDefinition::textarea(name: 'message', labelKey: 'notifications.fields.message')
                    ->attributes(['maxlength' => '1000'])
                    ->required(),
            )
            ->block(new CustomViewBlock(
                view: 'notifications::editor.audience',
                context: [
                    'notification' => $notification,
                    'roles' => $roles,
                    'input' => $input,
                    'selectedUsers' => $notification['audience_users'] ?? [],
                ],
            ))
            ->build();
    }

    /**
     * Sklada volby typu z canonical enumu
     *
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        $options = [];
        foreach (NotificationType::cases() as $type) {
            $options[$type->value] = '';
        }

        return $options;
    }

    /**
     * Prirazuje typum jejich lokalizacni klice
     *
     * @return array<string, string>
     */
    private function typeLabelKeys(): array
    {
        $keys = [];
        foreach (NotificationType::cases() as $type) {
            $keys[$type->value] = 'notifications.types.' . $type->value;
        }

        return $keys;
    }
}
