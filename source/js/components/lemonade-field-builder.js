/*!
 * Lemonade Field Builder
 *
 * Edits field presentation and twelve-column layouts using Admin controls.
 * Persistence, validation and permissions belong to the consuming module.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { I18n } from "../core/lemonade-i18n.js";
import { Sortable } from "./lemonade-sortable.js";
import { normalizeUrlSegment } from "./lemonade-url-segment.js";
import { Modal } from "./lemonade-modal.js";
import { mountComponents, destroyComponents } from "../lemonade-admin-components.js";

const instances = new WeakMap();
const element = (tag, className = "", text = "") => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;
    return node;
};
const button = (text, callback, className = "btn btn-light btn-sm") => {
    const node = element("button", className, text);
    node.type = "button";
    node.addEventListener("click", callback);
    return node;
};
const key = () => `new_${crypto.randomUUID()}`;

// A small DOM component: templates and labels are supplied by AdminEditor.
export class FieldBuilder {
    constructor(root) {
        this.root = root;
        this.input = root.closest("form").elements.namedItem(root.dataset.input);
        this.labels = JSON.parse(root.dataset.labels);
        this.kinds = JSON.parse(root.dataset.kinds);
        this.icons = JSON.parse(root.dataset.icons || "{}");
        this.translationPrefix = root.dataset.translationPrefix;
        this.refreshTranslations();
        this.state = JSON.parse(this.input.value);
        this.manualCodes = new Set(this.state.fields.filter((field) => field.code).map((field) => field.key));
        this.selected = this.state.fields.find((field) => !field.archived)?.key;
        this.list = root.querySelector("[data-builder-list]");
        this.layout = root.querySelector("[data-builder-layout]");
        this.inspector = root.querySelector("[data-builder-inspector]");
        this.preview = root.querySelector("[data-builder-preview]");
        this.status = root.querySelector("[data-builder-status]");
        this.abort = new AbortController();
        this.inspectorQueue = Promise.resolve();
        // Follow the resolved Bootstrap theme, including changes in system mode.
        this.themeObserver = new MutationObserver(() => {
            for (const frame of this.preview.querySelectorAll("[data-builder-rich-preview]")) {
                const field = this.field(frame.dataset.builderRichPreview);
                if (field) frame.srcdoc = this.richPreviewDocument(field.default_value);
            }
        });
        this.themeObserver.observe(document.documentElement, {
            attributes: true, attributeFilter: ["data-bs-theme"]
        });
        const previewModes = root.querySelectorAll("[data-builder-preview-mode]");
        const viewport = root.querySelector("[data-builder-preview-viewport]");
        previewModes.forEach((button) => button.addEventListener("click", () => {
            viewport.dataset.builderPreviewViewport = button.dataset.builderPreviewMode;
            previewModes.forEach((option) => {
                const active = option === button;
                option.classList.toggle("active", active);
                option.setAttribute("aria-pressed", String(active));
            });
        }, { signal: this.abort.signal }));
        document.addEventListener("lemonade:locale:changed", () => {
            this.refreshTranslations();
            this.status.textContent = "";
            this.render();
            this.showInspector();
        }, { signal: this.abort.signal });
        root.addEventListener("dragstart", (event) => {
            const palette = event.target.closest("[data-builder-add]");
            if (!palette) return;
            this.dragKind = palette.dataset.builderAdd;
            event.dataTransfer.effectAllowed = "copy";
            event.dataTransfer.setData("text/plain", this.dragKind);
        }, { signal: this.abort.signal });
        root.addEventListener("dragend", () => { this.dragKind = null; }, { signal: this.abort.signal });
        this.layout.addEventListener("dragover", (event) => {
            if (!this.dragKind) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = "copy";
        }, { signal: this.abort.signal });
        this.layout.addEventListener("drop", (event) => {
            if (!this.dragKind) return;
            event.preventDefault();
            const position = event.target.closest("[data-position]")?.dataset.position;
            this.add(this.dragKind, position === undefined ? this.state.rows.length : Number(position));
            this.dragKind = null;
        }, { signal: this.abort.signal });
        const form = root.closest("form");
        form.addEventListener("lemonade:dirty-state:discard", () => {
            this.modal?.close();
            this.state = JSON.parse(this.input.value);
            this.manualCodes = new Set(this.state.fields.filter((field) => field.code).map((field) => field.key));
            this.selected = this.state.fields.find((field) => !field.archived)?.key;
            this.render();
            this.showInspector();
        }, { signal: this.abort.signal });
        const nameInput = form.elements.namedItem(root.dataset.nameInput);
        const codeInput = form.elements.namedItem(root.dataset.codeInput);
        if (nameInput && codeInput && !codeInput.readOnly) {
            let manual = Boolean(codeInput.value);
            codeInput.addEventListener("input", () => { manual = true; }, { signal: this.abort.signal });
            nameInput.addEventListener("input", () => {
                if (!manual) codeInput.value = normalizeUrlSegment(nameInput.value, 64).replace(/-/g, "_");
            }, { signal: this.abort.signal });
        }
        this.layout.addEventListener("dragend", () => queueMicrotask(() => {
            if (!this.abort.signal.aborted) this.render();
        }), { signal: this.abort.signal });
        root.addEventListener("click", (event) => {
            const add = event.target.closest("[data-builder-add]");
            if (add) this.add(add.dataset.builderAdd);
        }, { signal: this.abort.signal });
        this.layout.addEventListener("lemonade:sortable:change", (event) => this.sorted(event), { signal: this.abort.signal });
        this.layout.addEventListener("lemonade:sortable:drop-zone", (event) => {
            const field = this.field(event.detail.itemId);
            if (!field) return;
            this.state.rows.forEach((row) => { row.columns = row.columns.filter((column) => column.field !== field.key); });
            this.state.rows.splice(Number(event.target.dataset.position), 0, { columns: [{ field: field.key, span: Number(field.span || 12) }] });
            this.changed();
        }, { signal: this.abort.signal });
        this.inspector.addEventListener("input", (event) => this.inspect(event), { signal: this.abort.signal });
        this.inspector.addEventListener("change", (event) => this.inspect(event), { signal: this.abort.signal });
        this.render();
        this.showInspector();
    }

    field(id) { return this.state.fields.find((field) => field.key === id); }

    add(kind, position = this.state.rows.length) {
        const field = { key: key(), name: this.kinds[kind], code: "", kind, required: 0, localized: 1, help: "", default_value: "", span: 12, options: [], archived: false };
        if (kind === "decimal") field.decimal_places = 2;
        this.state.fields.push(field);
        this.state.rows.splice(position, 0, { columns: [{ field: field.key, span: 12 }] });
        this.selected = field.key;
        this.changed();
        this.showInspector();
    }

    changed() {
        this.state.rows = this.state.rows.filter((row) => row.columns.length);
        const order = this.state.rows.flatMap((row) => row.columns.map((column) => column.field));
        this.state.fields.sort((a, b) => (order.indexOf(a.key) < 0 ? 1000 : order.indexOf(a.key)) - (order.indexOf(b.key) < 0 ? 1000 : order.indexOf(b.key)));
        this.input.value = JSON.stringify(this.state);
        this.input.dispatchEvent(new Event("input", { bubbles: true }));
        this.render();
    }

    sorted(event) {
        event.stopPropagation();
        const rows = [...this.layout.querySelectorAll("[data-builder-row]")].map((node) => {
            const original = this.state.rows[Number(node.dataset.builderRow)];
            return { ...original, columns: [...node.querySelectorAll(":scope > [data-lemonade-sortable-item]")].map((item) => ({ field: item.dataset.lemonadeSortableItem, span: Number(this.field(item.dataset.lemonadeSortableItem).span || 12) })) };
        });
        if (rows.some((row) => row.columns.reduce((sum, column) => sum + column.span, 0) > 12)) {
            this.status.textContent = this.labels.capacity;
            this.render();
            return;
        }
        this.state.rows = rows;
        this.changed();
    }

    select(field) {
        this.selected = field.key;
        this.render();
        this.showInspector();
    }

    refreshTranslations() {
        for (const [group, values] of [["builder", this.labels], ["kinds", this.kinds]]) {
            for (const name of Object.keys(values)) {
                const key = `${this.translationPrefix}.${group}.${name}`;
                const value = I18n.t(key);
                if (value !== key) values[name] = value;
            }
        }
    }

    textNode(tag, label, className = "") {
        const node = element(tag, className, this.labels[label]);
        node.dataset.lemonadeI18n = `${this.translationPrefix}.builder.${label}`;
        return node;
    }

    action(label, callback, className = "btn btn-light btn-sm", icon = null) {
        const node = button("", callback, className);
        if (icon) {
            const symbol = element("i", `bi bi-${icon} me-2`);
            symbol.setAttribute("aria-hidden", "true");
            node.append(symbol);
        }
        node.append(this.textNode("span", label));
        return node;
    }

    emptyState(icon, title, description, add = false) {
        const node = element("div", "lm-field-builder-empty");
        const symbol = element("i", `bi bi-${icon}`);
        symbol.setAttribute("aria-hidden", "true");
        node.append(symbol, this.textNode("h3", title), this.textNode("p", description));
        if (add) node.append(this.action("add_first", () => this.root.querySelector("[data-builder-add]")?.focus(), "btn btn-primary", "plus-lg"));
        return node;
    }

    // Keyboard and menu movement use the same state and width constraints as dragging.
    move(fieldKey, offset) {
        const positions = this.state.rows.flatMap((row) => row.columns.map((column, index) => ({ row, column, index })));
        const index = positions.findIndex((item) => item.column.field === fieldKey);
        const source = positions[index];
        const target = positions[index + offset];
        if (!source || !target) return;
        const sum = (row) => row.columns.reduce((total, column) => total + column.span, 0);
        if (source.row !== target.row && (sum(source.row) - source.column.span + target.column.span > 12 || sum(target.row) - target.column.span + source.column.span > 12)) {
            this.status.textContent = this.labels.capacity;
            return;
        }
        [source.row.columns[source.index], target.row.columns[target.index]] = [target.column, source.column];
        this.changed();
        this.focusHandle(fieldKey);
    }

    focusHandle(fieldKey) {
        this.layout.querySelector(`[data-lemonade-sortable-item="${CSS.escape(fieldKey)}"] [data-lemonade-sortable-handle]`)?.focus();
    }

    ownRow(fieldKey) {
        const index = this.state.rows.findIndex((row) => row.columns.some((column) => column.field === fieldKey));
        if (index < 0 || this.state.rows[index].columns.length === 1) return;
        const row = this.state.rows[index];
        const column = row.columns.find((item) => item.field === fieldKey);
        row.columns = row.columns.filter((item) => item !== column);
        this.state.rows.splice(index + 1, 0, { columns: [column] });
        this.changed();
        this.focusHandle(fieldKey);
    }

    render() {
        Sortable.destroy(this.layout);
        this.list.replaceChildren();
        this.layout.replaceChildren();
        this.preview.replaceChildren();
        const archived = this.state.fields.filter((field) => field.archived);
        this.root.querySelector("[data-builder-archived]").hidden = archived.length === 0;
        for (const field of archived) this.list.append(button(field.name, () => this.select(field), "lm-form-editor-field"));
        if (!this.state.rows.length) {
            this.layout.append(this.emptyState("window-plus", "empty_title", "empty", true));
            this.preview.append(this.emptyState("eye", "preview_empty_title", "preview_empty"));
            return;
        }
        const dropZone = (position) => {
            const last = position === this.state.rows.length;
            const zone = element("div", `lm-form-layout-drop-zone${last ? " lm-field-builder-drop-last" : ""}`);
            if (last) {
                const icon = element("i", "bi bi-box-arrow-in-down");
                icon.setAttribute("aria-hidden", "true");
                zone.append(icon);
            }
            zone.append(this.textNode("span", last ? "drop_more" : "new_row"));
            zone.dataset.lemonadeSortableDropZone = "";
            zone.dataset.lemonadeSortableGroup = this.input.id;
            zone.dataset.position = position;
            this.layout.append(zone);
        };
        dropZone(0);
        this.state.rows.forEach((row, index) => {
            const shell = element("div", "lm-form-layout-row");
            const columns = element("div", "row g-2 lm-form-layout-row-columns");
            columns.dataset.builderRow = index;
            columns.dataset.lemonadeSortable = "";
            columns.dataset.lemonadeSortableGroup = this.input.id;
            const previewRow = element("div", "row g-3");
            for (const column of row.columns) {
                const field = this.field(column.field);
                field.span = column.span;
                const node = element("div", `col-${column.span}`);
                node.dataset.lemonadeSortableItem = field.key;
                const card = element("div", `lm-form-layout-column-card${field.key === this.selected ? " is-selected" : ""}`);
                const handle = button("", () => this.select(field), "btn lm-field-builder-icon-button");
                handle.dataset.lemonadeSortableHandle = "";
                handle.title = this.labels.move_help;
                handle.setAttribute("aria-label", `${field.name}: ${this.labels.move_help}`);
                handle.append(element("i", "bi bi-grip-vertical"));
                handle.addEventListener("keydown", (event) => {
                    if (event.altKey && ["ArrowUp", "ArrowDown"].includes(event.key)) {
                        event.preventDefault();
                        this.move(field.key, event.key === "ArrowUp" ? -1 : 1);
                    }
                });
                const select = button("", () => this.select(field), "lm-field-builder-select");
                select.setAttribute("aria-pressed", String(field.key === this.selected));
                const icon = element("i", `bi bi-${this.icons[field.kind] || "input-cursor-text"}`);
                icon.setAttribute("aria-hidden", "true");
                const copy = element("span", "lm-form-editor-field-copy");
                copy.append(element("strong", "", field.name), element("small", "", this.kinds[field.kind]));
                select.append(icon, copy);
                const menu = element("details", "lm-field-builder-menu");
                const summary = element("summary");
                summary.setAttribute("aria-label", this.labels.field_actions);
                summary.append(element("i", "bi bi-three-dots-vertical"));
                const actions = element("div");
                actions.append(this.action("move_up", () => this.move(field.key, -1)), this.action("move_down", () => this.move(field.key, 1)), this.action("own_row", () => this.ownRow(field.key)));
                menu.append(summary, actions);
                const width = button(`${column.span} / 12`, () => {
                    this.select(field);
                    void this.inspectorQueue.then(() => {
                        const tabs = this.inspector.querySelector("[data-lemonade-tabs]");
                        tabs?.lemonadeTabs?.activate(tabs.querySelector('[data-builder-tab="display"]'));
                        const control = this.inspector.querySelector('[data-builder-property="span"]');
                        const target = control?.lemonadeSelect?.trigger || control;
                        target?.scrollIntoView({ block: "nearest" });
                        target?.focus();
                    }).catch((error) => console.error("Unable to focus field width.", error));
                }, "lm-form-layout-span");
                width.title = this.labels.edit_width;
                width.setAttribute("aria-label", `${field.name}: ${this.labels.edit_width}, ${column.span} / 12`);
                card.append(handle, select, width, menu);
                node.append(card);
                columns.append(node);
                previewRow.append(this.previewField(field, column.span));
            }
            shell.append(columns);
            this.layout.append(shell);
            this.preview.append(previewRow);
            dropZone(index + 1);
        });
        Sortable.mount(this.layout);
        void I18n.apply(this.preview).catch((error) => console.error("Unable to translate field preview.", error));
    }

    // Preview controls do not submit values. Rich HTML uses an isolated, non-scripted document.
    previewField(field, span) {
        const node = element("div", `col-${span}`);
        const label = element("label", "lm-form-label", field.name + (Number(field.required) ? " *" : ""));
        node.append(label, this.root.querySelector(`[data-builder-preview-template="${field.kind}"]`).content.cloneNode(true));
        const control = node.querySelector("input:not([type=hidden]),textarea,select");
        node.querySelectorAll("[id]").forEach((child) => { child.id += `-${field.key}`; });
        control.id = `lm-preview-${field.html_id || `field-${field.code || field.key}`}`;
        label.htmlFor = control.id;
        if (field.css_class) {
            const classes = field.css_class.trim().split(/\s+/).filter((name) => /^-?[_a-zA-Z][_a-zA-Z0-9-]*$/.test(name));
            control.classList.add(...classes);
        }
        node.querySelectorAll("[name]").forEach((child) => child.removeAttribute("name"));
        node.querySelectorAll("[data-lemonade-date-time]").forEach((child) => child.removeAttribute("data-lemonade-date-time"));
        if (control.tagName === "SELECT") {
            control.classList.remove("form-select");
            control.classList.add("form-control");
        }
        // Preview is never mounted as a second interactive editor or upload target.
        node.querySelectorAll("[data-lemonade-admin-rich-text]").forEach((child) => child.removeAttribute("data-lemonade-admin-rich-text"));
        if (control.tagName === "SELECT" && field.kind === "select") this.populateOptions(control, field);
        control.value = field.default_value ?? "";
        if (control.type === "checkbox") control.checked = String(field.default_value) === "1";
        control.disabled = true;
        if (field.kind === "richtext") {
            const frame = document.createElement("iframe");
            frame.className = "w-100 border rounded lm-field-builder-rich-preview";
            frame.id = control.id;
            frame.title = field.name;
            frame.setAttribute("sandbox", "");
            frame.dataset.builderRichPreview = field.key;
            frame.srcdoc = this.richPreviewDocument(field.default_value);
            control.replaceWith(frame);
        }
        node.append(element("small", "lm-form-help", field.help || ""));
        return node;
    }

    // Native document colors keep the sandbox readable without allowing styles or scripts.
    richPreviewDocument(value) {
        const scheme = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "dark" : "light";
        return '<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'">' +
            `<meta name="color-scheme" content="${scheme}"></head><body>` + (value || "") + '</body></html>';
    }

    populateOptions(control, field) {
        control.replaceChildren(new Option(this.labels.no_default, ""));
        for (const option of field.options) control.add(new Option(option.name, option.code));
    }

    showInspector() {
        // Serialize lifecycle work so rapid selections cannot leave stale editors mounted.
        this.inspectorQueue = this.inspectorQueue.then(async () => {
            this.inspectorBusy = true;
            this.inspectorTab = this.inspector.querySelector('[role="tab"][aria-selected="true"]')?.dataset.builderTab || this.inspectorTab;
            this.unbindRichText?.();
            this.unbindRichText = null;
            await destroyComponents(this.inspector);
            if (this.abort.signal.aborted) return;
            this.inspector.replaceChildren();
            const field = this.field(this.selected);
            if (!field) {
                this.inspector.append(this.emptyState("gear", "choose_field", "choose_field_help"));
                this.inspectorBusy = false;
                return;
            }
            if (field.archived) {
                this.inspector.append(this.action("restore", () => {
                    field.archived = false;
                    this.state.rows.push({ columns: [{ field: field.key, span: 12 }] });
                    this.changed();
                    this.showInspector();
                }));
                this.inspectorBusy = false;
                return;
            }
            this.inspector.append(this.root.querySelector(`[data-builder-inspector-template="${field.kind}"]`).content.cloneNode(true));
            for (const control of this.inspector.querySelectorAll("[name]")) {
                const name = control.name;
                control.removeAttribute("name");
                if (control.type === "hidden") continue;
                control.dataset.builderProperty = name;
                control.dataset.builderField = field.key;
                if (name === "default_value" && field.kind === "select") this.populateOptions(control, field);
                control.value = field[name] ?? "";
                if (control.type === "checkbox") {
                    control.value = "1";
                    control.checked = String(field[name]) === "1";
                }
                if (name === "code") control.readOnly = Boolean(field.id);
                if (name === "html_id") control.placeholder = `field-${field.code || field.key}`;
            }
            const actions = this.inspector.querySelector("[data-builder-field-actions]");
            if (field.kind === "select") actions.append(this.action("options", () => this.editOptions(field), "btn btn-outline-primary btn-sm", "list-ul"));
            actions.append(this.action(field.id ? "archive" : "remove_field", () => {
                field.archived = true;
                this.state.rows.forEach((row) => { row.columns = row.columns.filter((column) => column.field !== field.key); });
                // Unsaved fields have no stable identity to preserve.
                if (!field.id) this.state.fields = this.state.fields.filter((item) => item !== field);
                this.changed();
                this.showInspector();
            }, "btn btn-outline-danger btn-sm", field.id ? "archive" : "trash"));
            await I18n.apply(this.inspector);
            await mountComponents(this.inspector);
            const tabs = this.inspector.querySelector("[data-lemonade-tabs]");
            const activeTab = tabs?.querySelector(`[data-builder-tab="${this.inspectorTab || "properties"}"]`);
            if (activeTab) tabs.lemonadeTabs?.activate(activeTab);
            const control = this.inspector.querySelector('[data-builder-property="default_value"]');
            const editor = control?.lemonadeJoditRichText?.editor;
            if (editor) {
                // Subscribe to the existing editor, including toolbar/source changes.
                // Its value getter and clean-HTML lifecycle remain authoritative.
                const synchronize = () => this.inspect({ target: control });
                editor.e.on("change", synchronize);
                this.unbindRichText = () => editor.e.off("change", synchronize);
            }
            this.inspectorBusy = false;
        }).catch((error) => {
            this.inspectorBusy = false;
            if (!this.abort.signal.aborted) this.status.textContent = this.labels.error;
            console.error("Unable to mount field settings.", error);
        });
    }

    inspect(event) {
        const property = event.target.dataset.builderProperty;
        const field = this.field(this.selected);
        if (!property || !field || this.inspectorBusy || event.target.dataset.builderField !== field.key) return;
        if (property === "span") {
            const row = this.state.rows.find((item) => item.columns.some((column) => column.field === field.key));
            const span = Number(event.target.value);
            if (row.columns.reduce((sum, column) => sum + (column.field === field.key ? span : column.span), 0) > 12) {
                event.target.value = field.span;
                this.status.textContent = this.labels.capacity;
                return;
            }
            row.columns.find((column) => column.field === field.key).span = span;
        }
        if (property === "code") this.manualCodes.add(field.key);
        if (property === "name" && !field.id && !this.manualCodes.has(field.key)) {
            field.code = normalizeUrlSegment(event.target.value, 64).replace(/-/g, "_");
            this.inspector.querySelector('[data-builder-property="code"]').value = field.code;
            this.inspector.querySelector('[data-builder-property="html_id"]').placeholder = `field-${field.code}`;
        }
        const value = event.target.type === "checkbox"
            ? (event.target.checked ? 1 : 0)
            : (event.target.lemonadeJoditRichText?.editor?.value ?? event.target.value);
        // Jodit emits both native input/change and its own change event.
        // Keep a single state update so the sandbox preview is not repeatedly reset.
        if (String(field[property] ?? "") === String(value)) return;
        field[property] = value;
        this.status.textContent = "";
        this.changed();
    }

    editOptions(field) {
        const modal = Modal.create({ size: "medium", scrollable: true });
        this.modal = modal;
        const content = element("div", "lm-field-builder-options");
        const heading = element("div", "lm-field-builder-modal-heading");
        const title = this.textNode("h2", "options", "h5");
        const close = button("", () => modal.close(), "btn lm-field-builder-icon-button");
        close.append(element("i", "bi bi-x-lg"), this.textNode("span", "cancel", "visually-hidden"));
        heading.append(title, close);
        title.dataset.lemonadeModalTitle = "";
        const list = element("div", "lm-form-editor-option-list");
        list.dataset.lemonadeSortable = "";
        const error = element("p", "text-danger");
        error.setAttribute("role", "alert");
        const append = (option = { code: "", name: "" }) => {
            const fragment = this.root.querySelector("[data-builder-option-template]").content.cloneNode(true);
            const item = fragment.firstElementChild;
            const uid = key();
            item.dataset.lemonadeSortableItem = uid;
            item.optionIdentity = option.id;
            for (const input of item.querySelectorAll("input")) {
                const name = input.name;
                const oldId = input.id;
                input.id = `${oldId}-${uid}`;
                item.querySelector(`label[for="${oldId}"]`)?.setAttribute("for", input.id);
                input.removeAttribute("name");
                input.dataset.optionProperty = name;
                input.value = option[name] || "";
                input.maxLength = name === "code" ? 64 : 180;
                if (name === "code") input.readOnly = Boolean(option.id);
            }
            const nameInput = item.querySelector('[data-option-property="name"]');
            const codeInput = item.querySelector('[data-option-property="code"]');
            let customCode = Boolean(option.code);
            codeInput.addEventListener("input", () => { customCode = true; });
            nameInput.addEventListener("input", () => {
                if (!customCode) codeInput.value = normalizeUrlSegment(nameInput.value, 64).replace(/-/g, "_");
            });
            item.querySelector("[data-option-remove]").addEventListener("click", () => item.remove());
            item.querySelector("[data-option-up]").addEventListener("click", () => { if (item.previousElementSibling) list.insertBefore(item, item.previousElementSibling); });
            item.querySelector("[data-option-down]").addEventListener("click", () => { if (item.nextElementSibling) list.insertBefore(item.nextElementSibling, item); });
            item.querySelector("[data-option-handle]").addEventListener("keydown", (event) => {
                if (!event.altKey || !["ArrowUp", "ArrowDown"].includes(event.key)) return;
                event.preventDefault();
                if (event.key === "ArrowUp" && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
                if (event.key === "ArrowDown" && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
                event.target.focus();
            });
            list.append(item);
            void I18n.apply(item).catch((error) => console.error("Unable to translate field options.", error));
        };
        field.options.forEach(append);
        const actions = element("div", "lm-field-builder-modal-footer");
        actions.append(this.action("add_option", () => append(), "btn btn-outline-primary btn-sm", "plus-lg"), this.action("cancel", () => modal.close()), this.action("apply", () => {
            const options = [...list.children].map((item) => ({ ...(item.optionIdentity ? { id: item.optionIdentity } : {}), code: item.querySelector('[data-option-property="code"]').value.trim(), name: item.querySelector('[data-option-property="name"]').value.trim() }));
            if (!options.length || options.some((option) => !option.name || !/^[a-z][a-z0-9_]{0,63}$/.test(option.code)) || new Set(options.map((option) => option.code)).size !== options.length) {
                error.textContent = this.labels.invalid_options;
                error.dataset.lemonadeI18n = `${this.translationPrefix}.builder.invalid_options`;
                return;
            }
            field.options = options;
            if (!options.some((option) => option.code === field.default_value)) field.default_value = "";
            this.changed();
            this.showInspector();
            modal.close();
        }, "btn btn-primary btn-sm"));
        content.append(heading, error, list, actions);
        modal.setContent(content);
        Sortable.mount(list);
        modal.element.addEventListener("close", () => {
            Sortable.destroy(list);
            modal.destroy();
            if (this.modal === modal) this.modal = null;
        }, { once: true });
        modal.open();
    }

    async destroy() {
        this.abort.abort();
        this.themeObserver.disconnect();
        this.modal?.destroy();
        await this.inspectorQueue;
        this.unbindRichText?.();
        await destroyComponents(this.inspector);
        Sortable.destroy(this.layout);
    }
}

export function mount(root = document) {
    const nodes = [...(root.matches?.("[data-lemonade-field-builder]") ? [root] : []), ...root.querySelectorAll("[data-lemonade-field-builder]")];
    for (const node of nodes) if (!instances.has(node)) instances.set(node, new FieldBuilder(node));
}

export async function destroy(root = document) {
    const nodes = [...(root.matches?.("[data-lemonade-field-builder]") ? [root] : []), ...root.querySelectorAll("[data-lemonade-field-builder]")];
    for (const node of nodes) {
        await instances.get(node)?.destroy();
        instances.delete(node);
    }
}
