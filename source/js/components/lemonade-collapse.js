/*!
 * Lemonade Collapse
 *
 * Coordinates a local Admin disclosure panel without Bootstrap runtime dependencies.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
export class Collapse {
    constructor(root) {
        this.root = root;
        this.trigger = root.querySelector("[data-lemonade-collapse-trigger]");
        this.panel = root.querySelector("[data-lemonade-collapse-panel]");
        this.listenerRemovers = [];
        this.destroyed = false;
    }

    init() {
        if (!this.trigger || !this.panel) {
            return false;
        }

        this.setOpen(this.trigger.getAttribute("aria-expanded") === "true" && !this.panel.hidden);
        this.listenerRemovers.push(this.listen(this.trigger, "click", this.handleTriggerClick.bind(this)));

        return true;
    }

    open() {
        if (this.destroyed) {
            return false;
        }

        this.setOpen(true);

        return true;
    }

    close() {
        if (this.destroyed) {
            return false;
        }

        this.setOpen(false);

        return false;
    }

    toggle() {
        return this.isOpen() ? this.close() : this.open();
    }

    isOpen() {
        return !this.panel.hidden;
    }

    destroy() {
        if (this.destroyed) {
            return;
        }

        this.listenerRemovers.forEach(function (remove) { remove(); });
        this.listenerRemovers = [];
        delete this.root.lemonadeCollapse;
        this.destroyed = true;
    }

    setOpen(isOpen) {
        this.trigger.setAttribute("aria-expanded", String(isOpen));
        this.panel.hidden = !isOpen;
    }

    handleTriggerClick(event) {
        event.preventDefault();
        this.toggle();
    }

    listen(element, eventName, callback) {
        element.addEventListener(eventName, callback);

        return function () {
            element.removeEventListener(eventName, callback);
        };
    }

    static create(root) {
        root.lemonadeCollapse?.destroy();
        const collapse = new Collapse(root);

        if (!collapse.init()) {
            return null;
        }

        root.lemonadeCollapse = collapse;

        return collapse;
    }

    static mount(root = document) {
        const elements = root.matches?.("[data-lemonade-collapse]") ? [root] : [];
        elements.push(...root.querySelectorAll("[data-lemonade-collapse]"));
        elements.forEach(function (element) {
            if (!element.lemonadeCollapse) {
                Collapse.create(element);
            }
        });
    }

    static destroy(root = document) {
        const elements = root.matches?.("[data-lemonade-collapse]") ? [root] : [];
        elements.push(...root.querySelectorAll("[data-lemonade-collapse]"));
        elements.forEach(function (element) { element.lemonadeCollapse?.destroy(); });
    }
}

export function mount(root = document) {
    Collapse.mount(root);
}

export function destroy(root = document) {
    Collapse.destroy(root);
}
