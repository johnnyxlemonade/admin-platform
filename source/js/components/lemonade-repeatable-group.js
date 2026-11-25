/*!
 * Lemonade Repeatable Group
 *
 * Adds and removes repeatable form items while shared Sortable owns ordering
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { destroyComponents, mountComponents } from "../lemonade-admin-components.js";

const instances = new WeakMap();

/**
 * Manages item creation and removal for one server-rendered repeatable group
 */
export class RepeatableGroup {
    /**
     * Binds controls within one repeatable group
     *
     * @param {HTMLElement} element
     */
    constructor(element) {
        this.element = element;
        this.list = element.querySelector("[data-lemonade-repeatable-list]");
        this.template = element.querySelector("[data-lemonade-repeatable-template]");
        this.empty = element.querySelector("[data-lemonade-repeatable-empty]");
        this.abort = new AbortController();
        element.addEventListener("click", (event) => {
            if (event.target.closest("[data-lemonade-repeatable-add]")) {
                void this.add();
                return;
            }
            const remove = event.target.closest("[data-lemonade-repeatable-remove]");
            if (remove) {
                void this.remove(remove.closest("[data-lemonade-repeatable-item]"));
            }
        }, { signal: this.abort.signal });
        element.addEventListener("lemonade:sortable:change", () => {
            this.element.dispatchEvent(new Event("input", { bubbles: true }));
        }, { signal: this.abort.signal });
        this.updateEmptyState();
    }

    /**
     * Appends a fresh item and mounts its standard Admin controls
     *
     * @returns {Promise<void>}
     */
    async add() {
        const token = `new_${crypto.randomUUID()}`;
        const html = this.template.innerHTML.replaceAll("__item__", token);
        const fragment = document.createRange().createContextualFragment(html);
        const item = fragment.firstElementChild;
        this.list.append(item);
        await mountComponents(item);
        this.updateEmptyState();
        item.querySelector("input, textarea, select")?.focus();
        item.dispatchEvent(new Event("input", { bubbles: true }));
    }

    /**
     * Destroys mounted controls before removing an item
     *
     * @param {HTMLElement|null} item
     * @returns {Promise<void>}
     */
    async remove(item) {
        if (item === null) {
            return;
        }
        await destroyComponents(item);
        item.remove();
        this.updateEmptyState();
        this.element.dispatchEvent(new Event("input", { bubbles: true }));
    }

    /**
     * Shows the empty message only when the ordered list has no items
     *
     * @returns {void}
     */
    updateEmptyState() {
        this.empty.hidden = this.list.querySelector("[data-lemonade-repeatable-item]") !== null;
    }

    /**
     * Releases event listeners owned by the group
     *
     * @returns {void}
     */
    destroy() {
        this.abort.abort();
    }
}

/**
 * Mounts repeatable groups under a DOM root
 *
 * @param {Document|HTMLElement} root
 * @returns {void}
 */
export function mount(root = document) {
    const nodes = [
        ...(root.matches?.("[data-lemonade-repeatable-group]") ? [root] : []),
        ...root.querySelectorAll("[data-lemonade-repeatable-group]"),
    ];
    for (const node of nodes) {
        if (!instances.has(node)) {
            instances.set(node, new RepeatableGroup(node));
        }
    }
}

/**
 * Destroys repeatable groups under a DOM root
 *
 * @param {Document|HTMLElement} root
 * @returns {void}
 */
export function destroy(root = document) {
    const nodes = [
        ...(root.matches?.("[data-lemonade-repeatable-group]") ? [root] : []),
        ...root.querySelectorAll("[data-lemonade-repeatable-group]"),
    ];
    for (const node of nodes) {
        instances.get(node)?.destroy();
        instances.delete(node);
    }
}
