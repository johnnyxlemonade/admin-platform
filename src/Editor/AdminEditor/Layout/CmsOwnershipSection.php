<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Editor\AdminEditor\Field\Field;

/**
 * Sklada auditni metadata a autorizovanou spravu vlastnika
 */
final class CmsOwnershipSection
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori panel vlastnictvi s volitelnou akci zmeny vlastnika
     *
     * @param array<string,mixed> $content
     * @param array<int,string> $ownerOptions
     */
    public static function create(string $module, array $content, bool $canAssignOwner, array $ownerOptions): SectionBlock
    {
        $metadata = [
            [
                'labelKey' => $module . '.fields.created_by',
                'value' => trim((string) ($content['created_by_name'] ?? '')),
                'detail' => trim((string) ($content['created_at'] ?? '')),
            ],
            [
                'labelKey' => $module . '.fields.updated_by',
                'value' => trim((string) ($content['updated_by_name'] ?? '')),
                'detail' => trim((string) ($content['updated_at'] ?? '')),
            ],
            [
                'labelKey' => $module . '.fields.owner',
                'value' => trim((string) ($content['owner_name'] ?? '')),
                'detail' => null,
                'wide' => true,
                'current' => true,
            ],
        ];
        if (($content['deleted_at'] ?? null) !== null) {
            $metadata[] = [
                'labelKey' => $module . '.fields.deleted_by',
                'value' => trim((string) ($content['deleted_by_name'] ?? '')),
                'detail' => trim((string) ($content['deleted_at'] ?? '')),
                'wide' => true,
            ];
        }

        $blocks = [new CustomViewBlock('admin::components.editor-audit-metadata', ['metadata' => $metadata])];
        if ($canAssignOwner) {
            $blocks[] = new FieldGroupBlock([new FieldColumn(
                Field::actionSelect('owner_id', 'assign-owner', labelKey: $module . '.actions.assign_owner', actionLabelKey: $module . '.actions.assign_owner_submit')
                    ->options($ownerOptions)
                    ->attributes(['data-lemonade-searchable' => true]),
                md: 12,
            )]);
        }

        return new SectionBlock(
            id: 'ownership',
            blocks: $blocks,
            title: '',
            titleKey: $module . '.editor.ownership',
            descriptionKey: $module . '.editor.sections.ownership_help',
        );
    }
}
