<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Rendering;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\Action\AdminEditorActionDefinition;
use Lemonade\Admin\Editor\AdminEditor\Action\AdminEditorSaveBarDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBlock;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\HiddenField;
use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderContextDefinition;
use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderContextItem;
use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderDefinition;
use Lemonade\Admin\Editor\AdminEditor\Layout\AdminEditorSidebarDefinition;
use Lemonade\Admin\Editor\AdminEditor\Layout\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\Layout\CustomViewBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * Vykresluje cast administracniho editoru do HTML
 */
final readonly class AdminEditorRenderer
{
    private const HEADER_CONTEXT_SEARCH_THRESHOLD = 6;

    private AdminEditorFieldRenderer $fields;

    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private ?View $view = null,
        private ?ViewHelpers $helpers = null,
        ?AdminEditorFieldRenderer $fields = null,
    ) {
        $this->fields = $fields ?? new AdminEditorFieldRenderer($helpers);
    }

    /**
     * Vykresluje editor do HTML podle predane definice a kontextu
     */
    public function render(AdminEditorDefinition $editor, AdminEditorRenderContext $context): string
    {
        $form = $editor->form();
        $html = '<div class="admin-editor" data-lemonade-admin-editor="' . $this->escape($editor->id()) . '" data-lemonade-editor-mode="' . $this->escape($context->mode()) . '">';
        $html .= $this->header($editor->header(), $form);
        $html .= '<form id="' . $this->escape($form->id()) . '" method="' . $this->escape($form->method()) . '" action="' . $this->escape($form->action()) . '" data-lemonade-action-form data-lemonade-editor-form' . ($form->navigateToEditAfterCreate() ? ' data-lemonade-navigate-to-edit-after-create' : '') . ($form->novalidate() ? ' novalidate' : '') . '>';
        if ($form->csrf() && $this->helpers !== null) {
            $html .= $this->helpers->csrfField();
        }
        if (isset($context->errors()['_form'])) {
            $html .= '<div class="alert alert-danger" role="alert">' . $this->escape($this->firstError($context->errors()['_form'])) . '</div>';
        }
        $html .= $this->content($editor, $context, $form);
        $html .= '</form>' . $this->saveBar($editor->saveBar(), $form) . '</div>';
        return $html;
    }

    /**
     * Zpracovava hodnotu header v konfiguraci editoru
     */
    private function header(?AdminEditorHeaderDefinition $header, AdminActionFormDefinition $form): string
    {
        if ($header === null) {
            return '';
        }
        $html = '<header class="page-heading"><div class="admin-editor-header-main">';
        if ($header->breadcrumbs() !== []) {
            $html .= '<nav aria-label="Breadcrumb"><ol class="breadcrumb">';
            foreach ($header->breadcrumbs() as $breadcrumb) {
                $current = ($breadcrumb['current'] ?? false) === true;
                $label = $this->text($breadcrumb['label'], $breadcrumb['labelKey'] ?? null);
                $translationAttribute = $this->translationAttribute($breadcrumb['labelKey'] ?? null);
                $html .= '<li class="breadcrumb-item' . ($current ? ' active' : '') . '"' . ($current ? ' aria-current="page"' : '') . '>';
                $html .= isset($breadcrumb['href']) && !$current
                    ? '<a href="' . $this->escape($breadcrumb['href']) . '"' . $translationAttribute . '>' . $this->escape($label) . '</a>'
                    : '<span' . $translationAttribute . '>' . $this->escape($label) . '</span>';
                $html .= '</li>';
            }
            $html .= '</ol></nav>';
        }
        $title = '<h1' . $this->translationAttribute($header->titleKey()) . '>' . $this->escape($this->text($header->title(), $header->titleKey())) . '</h1>';
        $context = $header->context();
        if ($context !== null && count($context->items()) > 1) {
            $html .= '<div class="admin-editor-header-title-context">' . $title . $this->headerContext($context) . '</div>';
        } else {
            $html .= $title;
        }
        if ($header->description() !== null || $header->descriptionKey() !== null) {
            $html .= '<p' . $this->translationAttribute($header->descriptionKey()) . '>' . $this->escape($this->text($header->description() ?? '', $header->descriptionKey())) . '</p>';
        }
        if ($header->metadata() !== []) {
            $html .= '<p class="admin-editor-metadata">' . $this->escape(implode(' · ', $header->metadata())) . '</p>';
        }
        $html .= '</div>';
        if ($header->actions() !== []) {
            $html .= '<div class="d-flex gap-2">';
            foreach ($header->actions() as $action) {
                $html .= $this->action($action, $form, headerAction: true);
            } $html .= '</div>';
        }
        return $html . '</header>';
    }

    /**
     * Vykresli linkove prepinani kontextu v hlavicce editoru
     */
    private function headerContext(AdminEditorHeaderContextDefinition $context): string
    {
        $active = array_values(array_filter(
            $context->items(),
            static fn(AdminEditorHeaderContextItem $item): bool => $item->active(),
        ))[0] ?? $context->items()[0];
        $html = '<div class="lm-editor-header-context" data-lemonade-editor-header-context data-lemonade-dropdown data-lemonade-dropdown-placement="bottom-end" data-lemonade-dropdown-close-on-inside="false">';
        $html .= '<button class="btn btn-light lm-editor-header-context-trigger" type="button" data-lemonade-dropdown-trigger aria-haspopup="dialog" aria-expanded="false" aria-label="' . $this->escape($context->label()) . '">';
        $html .= '<span>' . $this->escape($active->label()) . '</span>';
        if ($active->code() !== null) {
            $html .= '<small>' . $this->escape($active->code()) . '</small>';
        }
        $html .= '<i class="bi bi-chevron-down" aria-hidden="true"></i></button>';
        $html .= '<div class="lm-dropdown-menu lm-editor-header-context-menu" data-lemonade-dropdown-panel hidden role="dialog" aria-label="' . $this->escape($context->label()) . '">';
        $html .= '<div class="lm-editor-header-context-heading"><strong>' . $this->escape($context->label()) . '</strong>';
        $counts = $context->translationCounts();
        if ($counts !== null) {
            $html .= '<span>' . $this->escape($this->text(
                '{existing} of {total} translations',
                'admin.editor.language_versions_count',
                $counts,
            )) . '</span>';
        }
        $html .= '</div>';
        if (count($context->items()) >= self::HEADER_CONTEXT_SEARCH_THRESHOLD) {
            $searchLabel = $this->text('Search languages', 'admin.editor.language_versions_search');
            $html .= '<div class="lm-search lm-editor-header-context-search">';
            $html .= '<i class="' . $this->escape(AdminIcon::Search->cssClass()) . ' lm-search-icon" aria-hidden="true"></i>';
            $html .= '<input class="form-control" type="search" autocomplete="off" spellcheck="false" placeholder="' . $this->escape($searchLabel) . '" aria-label="' . $this->escape($searchLabel) . '" data-lemonade-i18n-placeholder="admin.editor.language_versions_search" data-lemonade-i18n-aria-label="admin.editor.language_versions_search" data-lemonade-editor-header-context-search>';
            $html .= '</div>';
        }
        $html .= '<ul class="lm-editor-header-context-list" data-lemonade-editor-header-context-list>';
        foreach ($context->items() as $item) {
            $html .= $this->headerContextItem($item);
        }
        $html .= '</ul>';
        $html .= '<p class="lm-editor-header-context-empty" data-lemonade-editor-header-context-empty role="status" hidden>';
        $html .= $this->escape($this->text('No languages match your search.', 'admin.editor.language_versions_empty'));
        $html .= '</p>';

        return $html . '</div></div>';
    }

    /**
     * Vykresli jednu jazykovou volbu se stavem a aktivnim oznacenim
     */
    private function headerContextItem(AdminEditorHeaderContextItem $item): string
    {
        $code = $item->code();
        $html = '<li data-lemonade-editor-header-context-item>';
        $html .= '<a class="lm-dropdown-item' . ($item->active() ? ' is-active' : '') . '" href="' . $this->escape($item->href()) . '"' . ($item->active() ? ' aria-current="page"' : '') . ' data-lemonade-editor-header-context-link data-lemonade-editor-header-context-code="' . $this->escape($code ?? '') . '">';
        $html .= '<span class="lm-editor-header-context-language"><span>' . $this->escape($item->label()) . '</span>';
        if ($code !== null) {
            $html .= '<small>' . $this->escape($code) . '</small>';
        }
        $html .= '</span>';
        if ($item->secondary() !== null) {
            $html .= '<small class="lm-editor-header-context-status">' . $this->escape($item->secondary()) . '</small>';
        }
        $html .= '<span class="lm-editor-header-context-check" aria-hidden="true"><i class="' . $this->escape(AdminIcon::CheckLg->cssClass()) . '"></i></span>';

        return $html . '</a></li>';
    }

    /**
     * Zpracovava hodnotu tabs v konfiguraci editoru
     */
    private function tabs(AdminEditorDefinition $editor): string
    {
        if ($editor->tabs() === []) {
            return '';
        }
        $html = '<div class="lm-tabs-list" role="tablist">';
        foreach ($editor->tabs() as $tab) {
            $active = $tab->default();
            $html .= '<button id="' . $this->escape($this->tabButtonId($editor, $tab)) . '" class="lm-tab' . ($active ? ' is-active' : '') . '" type="button" role="tab" aria-controls="' . $this->escape($this->tabPaneId($editor, $tab)) . '" aria-selected="' . ($active ? 'true' : 'false') . '" tabindex="' . ($active ? '0' : '-1') . '"' . $this->translationAttribute($tab->labelKey()) . '>' . $this->escape($this->text($tab->label(), $tab->labelKey()));
            $badge = $tab->badge();
            if ($badge !== null) {
                $html .= '<span class="tab-count">' . $this->escape($badge) . '</span>';
            }
            $html .= '</button>';
        }
        return $html . '</div>';
    }

    /**
     * Zpracovava hodnotu content v konfiguraci editoru
     */
    private function content(
        AdminEditorDefinition $editor,
        AdminEditorRenderContext $context,
        AdminActionFormDefinition $form,
    ): string {
        $sidebar = $editor->sidebar();
        if ($editor->tabs() === []) {
            $main = $this->blocks($editor->blocks(), $editor, $context, $form);

            return $this->layout($main, $sidebar, $editor, $context, $form);
        }

        $tabs = $this->tabs($editor);
        $panels = $this->tabContent($editor, $context, $form);
        return '<div class="lm-tabs admin-editor-tabs" data-lemonade-tabs>' . $tabs . $this->layout($panels, $sidebar, $editor, $context, $form, afterTabs: true) . '</div>';
    }

    /**
     * Vykresluje shared main a optional sidebar sloupce pod spolecnym obsahem editoru
     */
    private function layout(
        string $main,
        ?AdminEditorSidebarDefinition $sidebar,
        AdminEditorDefinition $editor,
        AdminEditorRenderContext $context,
        AdminActionFormDefinition $form,
        bool $afterTabs = false,
    ): string {
        if ($sidebar === null || $sidebar->panels() === []) {
            if (!$afterTabs) {
                return '<div class="admin-editor-main">' . $main . '</div>';
            }

            return '<div class="admin-editor-layout"><main class="admin-editor-main">' . $main . '</main></div>';
        }

        return '<div class="admin-editor-layout"><main class="admin-editor-main">' . $main . '</main><aside class="admin-editor-sidebar">' . $this->blocks($sidebar->panels(), $editor, $context, $form) . '</aside></div>';
    }

    /**
     * Zpracovava hodnotu tabcontent v konfiguraci editoru
     */
    private function tabContent(
        AdminEditorDefinition $editor,
        AdminEditorRenderContext $context,
        AdminActionFormDefinition $form,
    ): string {
        $html = '<div class="lm-tab-panels">';
        foreach ($editor->tabs() as $tab) {
            $html .= '<section class="lm-tab-panel" id="' . $this->escape($this->tabPaneId($editor, $tab)) . '" role="tabpanel" aria-labelledby="' . $this->escape($this->tabButtonId($editor, $tab)) . '"' . ($tab->default() ? '' : ' hidden') . '>' . $this->blocks($tab->blocks(), $editor, $context, $form) . '</section>';
        }
        return $html . '</div>';
    }

    /**
     * Zpracovava hodnotu blocks v konfiguraci editoru
     * @param list<AdminEditorBlock> $blocks
     */
    private function blocks(
        array $blocks,
        AdminEditorDefinition $editor,
        AdminEditorRenderContext $context,
        AdminActionFormDefinition $form,
    ): string {
        $html = '';
        foreach ($blocks as $block) {
            if ($block instanceof AdminEditorFieldDefinition) {
                $html .= $this->fields->render($block, $context, $editor->id(), $form);
                continue;
            }
            if ($block instanceof FieldGroupBlock) {
                $html .= $this->fieldGroup($block, $editor, $context, $form);
                continue;
            }
            if ($block instanceof CustomViewBlock) {
                $html .= '<div class="admin-editor-custom-block">' . $this->customView($block) . '</div>';
                continue;
            }
            if ($block instanceof SectionBlock) {
                $html .= $this->section($block, $editor, $context, $form);
                continue;
            }

            throw new \LogicException(sprintf('Unsupported AdminEditor block "%s".', $block::class));
        }
        return $html;
    }

    /**
     * Zpracovava hodnotu section v konfiguraci editoru
     */
    private function section(
        SectionBlock $section,
        AdminEditorDefinition $editor,
        AdminEditorRenderContext $context,
        AdminActionFormDefinition $form,
    ): string {
        $hasTitle = $section->title() !== null || $section->titleKey() !== null;
        $sectionHeadingId = $this->sectionHeadingId($editor, $section);
        $html = '<section class="card lm-card admin-editor-section mb-4"' . ($hasTitle ? ' aria-labelledby="' . $this->escape($sectionHeadingId) . '"' : '') . '><div class="card-body">';
        if ($hasTitle) {
            $html .= '<h2 id="' . $this->escape($sectionHeadingId) . '" class="h5 admin-editor-section-title"' . $this->translationAttribute($section->titleKey()) . '>' . $this->escape($this->text($section->title() ?? '', $section->titleKey())) . '</h2>';
        }
        if ($section->description() !== null || $section->descriptionKey() !== null) {
            $html .= '<p class="lm-form-help admin-editor-section-description"' . $this->translationAttribute($section->descriptionKey()) . '>' . $this->escape($this->text($section->description() ?? '', $section->descriptionKey())) . '</p>';
        }
        return $html . $this->blocks($section->blocks(), $editor, $context, $form) . '</div></section>';
    }

    /**
     * Zpracovava hodnotu fieldgroup v konfiguraci editoru
     */
    private function fieldGroup(
        FieldGroupBlock $group,
        AdminEditorDefinition $editor,
        AdminEditorRenderContext $context,
        AdminActionFormDefinition $form,
    ): string {
        $html = '<div class="row g-3 admin-editor-field-group">';
        foreach ($group->columns() as $column) {
            $field = $column->field();
            if ($field instanceof HiddenField) {
                $html .= $this->fields->render($field, $context, $editor->id(), $form);
                continue;
            }
            $html .= '<div class="' . $this->columnClasses($column) . '">' . $this->fields->render($field, $context, $editor->id(), $form) . '</div>';
        }
        return $html . '</div>';
    }

    /**
     * Zpracovava hodnotu columnclasses v konfiguraci editoru
     */
    private function columnClasses(FieldColumn $column): string
    {
        $classes = ['col-12'];
        foreach ($column->breakpoints() as $breakpoint => $span) {
            $classes[] = 'col-' . $breakpoint . '-' . $span;
        }

        return implode(' ', $classes);
    }

    /**
     * Zpracovava hodnotu customview v konfiguraci editoru
     */
    private function customView(CustomViewBlock $block): string
    {
        if ($this->view === null) {
            throw new \LogicException('AdminEditorRenderer requires a View to render CustomViewBlock.');
        }
        return $this->view->partial($block->view(), $block->context());
    }

    /**
     * Zpracovava hodnotu savebar v konfiguraci editoru
     */
    private function saveBar(?AdminEditorSaveBarDefinition $saveBar, AdminActionFormDefinition $form): string
    {
        if ($saveBar === null) {
            return '';
        }
        $html = '<div class="lm-save-bar" data-lemonade-save-bar hidden><div class="lm-save-bar-copy" role="status">';
        $title = $saveBar->title();
        if ($title !== null || $saveBar->titleKey() !== null) {
            $html .= '<strong' . $this->translationAttribute($saveBar->titleKey()) . '>' . $this->escape($this->text($title ?? '', $saveBar->titleKey())) . '</strong>';
        }
        $description = $saveBar->description();
        if ($description !== null || $saveBar->descriptionKey() !== null) {
            $html .= '<span' . $this->translationAttribute($saveBar->descriptionKey()) . '>' . $this->escape($this->text($description ?? '', $saveBar->descriptionKey())) . '</span>';
        }
        $html .= '</div><div class="lm-save-bar-actions">';
        $secondaryAction = $saveBar->secondaryAction();
        if ($secondaryAction !== null) {
            $html .= $this->discardAction($secondaryAction, $form);
        }
        return $html . $this->action($saveBar->primaryAction(), $form) . '</div></div>';
    }

    /**
     * Zpracovava hodnotu action v konfiguraci editoru
     */
    private function action(AdminEditorActionDefinition $action, AdminActionFormDefinition $form, bool $headerAction = false): string
    {
        $iconAction = $headerAction && in_array($action->labelKey(), ['admin.common.back', 'admin.common.save'], true);
        $class = 'btn ' . ($action->primary() ? 'btn-primary' : 'btn-light') . ($iconAction ? ' lm-button-with-icon' : '');
        $label = $this->text($action->label(), $action->labelKey());
        $attributes = $this->attributes($action->attributes()) . ($iconAction ? '' : $this->translationAttribute($action->labelKey()));
        $content = $iconAction ? $this->headerActionContent($action, $label) : $this->escape($label);
        if ($action->submit()) {
            $actionUrl = $form->actionUrl();
            $actionKey = $form->actionKey();
            if ($actionUrl !== null && $actionKey !== null) {
                $attributes .= ' data-lemonade-action data-lemonade-form="' . $this->escape($form->id()) . '" data-lemonade-url="' . $this->escape($actionUrl) . '" data-lemonade-method="' . $this->escape($form->method()) . '" data-lemonade-action-key="' . $this->escape($actionKey) . '"';
            }
            return '<button class="' . $class . '" type="submit" form="' . $this->escape($form->id()) . '"' . $attributes . '>' . $content . '</button>';
        }
        return '<a class="' . $class . '" href="' . $this->escape($action->href() ?? '#') . '"' . $attributes . '>' . $content . '</a>';
    }

    /**
     * Vytvari obsah standardni navigacni nebo ukladaci akce v hlavicce editoru
     */
    private function headerActionContent(AdminEditorActionDefinition $action, string $label): string
    {
        $icon = match ($action->labelKey()) {
            'admin.common.back' => AdminIcon::ArrowLeft,
            'admin.common.save' => AdminIcon::Floppy,
            default => null,
        };

        if ($icon === null) {
            return $this->escape($label);
        }

        return '<i class="' . $this->escape($icon->cssClass()) . '" aria-hidden="true"></i><span' . $this->translationAttribute($action->labelKey()) . '>' . $this->escape($label) . '</span>';
    }

    /**
     * Zpracovava hodnotu discardaction v konfiguraci editoru
     */
    private function discardAction(AdminEditorActionDefinition $action, AdminActionFormDefinition $form): string
    {
        $attributes = $this->attributes($action->attributes()) . $this->translationAttribute($action->labelKey());
        $label = $this->text($action->label(), $action->labelKey());

        return '<button class="btn btn-light" type="button" data-lemonade-editor-discard data-lemonade-form="' . $this->escape($form->id()) . '"' . $attributes . '>' . $this->escape($label) . '</button>';
    }

    /**
     * Zpracovava hodnotu tabpaneid v konfiguraci editoru
     */
    private function tabPaneId(AdminEditorDefinition $editor, AdminEditorTab $tab): string
    {
        return $editor->id() . '-tab-' . $tab->id();
    }

    /**
     * Zpracovava hodnotu tabbuttonid v konfiguraci editoru
     */
    private function tabButtonId(AdminEditorDefinition $editor, AdminEditorTab $tab): string
    {
        return $editor->id() . '-tab-button-' . $tab->id();
    }

    /**
     * Zpracovava hodnotu sectionheadingid v konfiguraci editoru
     */
    private function sectionHeadingId(AdminEditorDefinition $editor, SectionBlock $section): string
    {
        return $editor->id() . '-section-' . $section->id();
    }

    /**
     * Vraci text nebo jeho lokalizovanou variantu
     *
     * @param array<string,int|string> $replacements
     */
    private function text(string $value, ?string $key, array $replacements = []): string
    {
        if ($key !== null && $this->helpers !== null) {
            return $this->helpers->lang($key, $replacements);
        }

        foreach ($replacements as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * Zpracovava hodnotu translationattribute v konfiguraci editoru
     */
    private function translationAttribute(?string $key): string
    {
        return $key === null ? '' : ' data-lemonade-i18n="' . $this->escape($key) . '"';
    }

    /**
     * Vytvari HTML atributy z povoleneho nastaveni
     * @param array<string, scalar|bool|null> $attributes
     */
    private function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            if ($value !== false && $value !== null) {
                $html .= ' ' . $this->escape($key) . ($value === true ? '' : '="' . $this->escape((string) $value) . '"');
            }
        } return $html;
    }

    /**
     * Zpracovava hodnotu firsterror v konfiguraci editoru
     * @param list<string> $errors
     */
    private function firstError(string|array $errors): string
    {
        return is_array($errors) ? (string) ($errors[0] ?? '') : $errors;
    }

    /**
     * Escapuje text pro bezpecne vlozeni do HTML
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
