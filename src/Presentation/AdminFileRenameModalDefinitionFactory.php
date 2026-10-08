<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada shared modal pro upravu presentation metadat jedne file identity
 */
final class AdminFileRenameModalDefinitionFactory
{
    /**
     * Nastavuje generator canonical Admin route
     */
    public function __construct(private readonly UrlGenerator $urls) {}

    /**
     * Vytvori editor presentation metadat nad explicitnim file targetem
     */
    public function modal(string $module, string $usage, int $entityId, int $fileId): AdminEditorDefinition
    {
        $parameters = [
            'module' => $module,
            'usage' => $usage,
            'entity' => $entityId,
            'file' => $fileId,
        ];

        return AdminEditorBuilder::create('admin.file.presentation')
            ->form(new AdminEditorFormDefinition(
                id: 'admin-file-presentation-form',
                action: $this->urls->route('admin.file.collection.rename', $parameters),
                actionUrl: $this->urls->route('admin.file.collection.rename', $parameters),
                actionKey: 'edit',
                novalidate: true,
            ))
            ->block(new SectionBlock(
                id: 'presentation',
                blocks: [
                    AdminEditorFieldDefinition::text('display_name', labelKey: 'admin.file_upload.display_name')
                        ->required()
                        ->attributes(['maxlength' => '255']),
                    AdminEditorFieldDefinition::textarea('caption', labelKey: 'admin.file_upload.caption')
                        ->help('', 'admin.file_upload.caption_optional')
                        ->attributes(['maxlength' => '500', 'rows' => '3']),
                ],
            ))
            ->build();
    }
}
