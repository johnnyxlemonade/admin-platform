/*!
 * Lemonade Editor Header Context
 *
 * Adds searchable keyboard navigation to the shared AdminEditor context dropdown.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { Dropdown } from "./lemonade-dropdown.js";

class EditorHeaderContext {
    constructor(root) {
        this.root = root;
        this.search = root.querySelector("[data-lemonade-editor-header-context-search]");
        this.empty = root.querySelector("[data-lemonade-editor-header-context-empty]");
        this.items = DomHelper.queryAll("[data-lemonade-editor-header-context-item]", root);
        this.links = DomHelper.queryAll("[data-lemonade-editor-header-context-link]", root);
        this.dropdown = null;
        this.listenerRemovers = [];
    }

    init() {
        if (this.items.length === 0 || this.links.length === 0) {
            return false;
        }

        this.dropdown = Dropdown.create(this.root, {
            placement: "bottom-end",
            fixed: true,
            closeOnInsideClick: false,
            onOpen: this.handleOpen.bind(this),
            onClose: this.handleClose.bind(this),
        });
        if (!this.dropdown) {
            return false;
        }

        if (this.search) {
            this.listenerRemovers.push(EventHelper.on(this.search, "input", this.filter.bind(this)));
        }
        this.listenerRemovers.push(EventHelper.on(this.root, "keydown", this.handleKeydown.bind(this)));

        return true;
    }

    destroy() {
        this.listenerRemovers.forEach(function (remove) { remove(); });
        this.listenerRemovers = [];
        this.dropdown?.destroy();
        this.dropdown = null;
        delete this.root.lemonadeEditorHeaderContext;
    }

    handleOpen() {
        const target = this.search || this.links.find(function (link) {
            return link.getAttribute("aria-current") === "page";
        }) || this.links[0];

        target?.focus();
    }

    handleClose() {
        if (!this.search || this.search.value === "") {
            return;
        }

        this.search.value = "";
        this.filter();
    }

    filter() {
        const query = this.normalize(this.search?.value || "");
        let visibleCount = 0;

        this.items.forEach(function (item) {
            const link = item.querySelector("[data-lemonade-editor-header-context-link]");
            const language = link?.querySelector(".lm-editor-header-context-language > span")?.textContent || "";
            const code = link?.getAttribute("data-lemonade-editor-header-context-code") || "";
            const visible = query === "" || this.normalize(`${language} ${code}`).includes(query);

            item.hidden = !visible;
            visibleCount += visible ? 1 : 0;
        }, this);

        if (this.empty) {
            this.empty.hidden = visibleCount > 0;
        }
    }

    normalize(value) {
        return String(value)
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLocaleLowerCase();
    }

    handleKeydown(event) {
        if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) {
            return;
        }

        const visibleLinks = this.items.filter(function (item) {
            return !item.hidden;
        }).map(function (item) {
            return item.querySelector("[data-lemonade-editor-header-context-link]");
        }).filter(Boolean);
        if (visibleLinks.length === 0) {
            return;
        }

        const currentIndex = visibleLinks.indexOf(event.target.closest?.("[data-lemonade-editor-header-context-link]"));
        let nextIndex = 0;
        if (event.key === "ArrowUp") {
            nextIndex = currentIndex <= 0 ? visibleLinks.length - 1 : currentIndex - 1;
        } else if (event.key === "ArrowDown" && currentIndex >= 0) {
            nextIndex = currentIndex === visibleLinks.length - 1 ? 0 : currentIndex + 1;
        } else if (event.key === "End") {
            nextIndex = visibleLinks.length - 1;
        }

        event.preventDefault();
        visibleLinks[nextIndex].focus();
    }

    static create(root) {
        root.lemonadeEditorHeaderContext?.destroy();
        const context = new EditorHeaderContext(root);

        if (!context.init()) {
            return null;
        }

        root.lemonadeEditorHeaderContext = context;

        return context;
    }

    static mount(root = document) {
        const elements = root.matches?.("[data-lemonade-editor-header-context]") ? [root] : [];
        elements.push(...DomHelper.queryAll("[data-lemonade-editor-header-context]", root));
        elements.forEach(function (element) {
            if (!element.lemonadeEditorHeaderContext) {
                EditorHeaderContext.create(element);
            }
        });
    }

    static destroy(root = document) {
        const elements = root.matches?.("[data-lemonade-editor-header-context]") ? [root] : [];
        elements.push(...DomHelper.queryAll("[data-lemonade-editor-header-context]", root));
        elements.forEach(function (element) { element.lemonadeEditorHeaderContext?.destroy(); });
    }
}

export function mount(root = document) {
    EditorHeaderContext.mount(root);
}

export function destroy(root = document) {
    EditorHeaderContext.destroy(root);
}
