/*!
 * Lemonade Dropdown
 *
 * Owns small Admin floating panels without Bootstrap runtime dependencies.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";

const supportedPlacements = ["bottom-end", "top-end", "right-start"];

export class Dropdown {
    constructor(root, options = {}) {
        this.root = root;
        this.trigger = options.trigger || root.querySelector("[data-lemonade-dropdown-trigger]");
        this.panel = options.panel || root.querySelector("[data-lemonade-dropdown-panel]");
        this.placement = supportedPlacements.includes(options.placement) ? options.placement : "bottom-end";
        this.fixed = options.fixed === true;
        this.closeOnInsideClick = options.closeOnInsideClick !== false;
        this.returnFocus = options.returnFocus !== false;
        this.onOpen = typeof options.onOpen === "function" ? options.onOpen : null;
        this.onClose = typeof options.onClose === "function" ? options.onClose : null;
        this.relatedRoots = options.relatedRoots || null;
        this.listenerRemovers = [];
        this.openListenerRemovers = [];
        this.destroyed = false;
    }

    init() {
        if (!this.trigger || !this.panel) {
            return false;
        }

        this.root.classList.add("lm-dropdown");
        this.root.setAttribute("data-lemonade-dropdown", "");
        this.root.dataset.lemonadeDropdownPlacement = this.placement;
        this.panel.hidden = true;
        this.trigger.setAttribute("aria-expanded", "false");
        this.listenerRemovers.push(EventHelper.on(this.trigger, "click", this.handleTriggerClick.bind(this)));

        return true;
    }

    toggle() {
        if (this.isOpen()) {
            this.close("toggle");
            return;
        }

        this.open();
    }

    open() {
        if (this.destroyed || this.isOpen()) {
            return;
        }

        this.panel.hidden = false;
        this.root.classList.add("is-open");
        this.trigger.setAttribute("aria-expanded", "true");
        this.position();
        this.bindOpenListeners();
        this.onOpen?.(this);
    }

    close(reason = "programmatic") {
        if (this.destroyed || !this.isOpen()) {
            return;
        }

        this.unbindOpenListeners();
        this.panel.hidden = true;
        this.root.classList.remove("is-open");
        this.trigger.setAttribute("aria-expanded", "false");
        this.onClose?.(reason, this);

        if (this.returnFocus && (reason === "escape" || reason === "apply" || reason === "select")) {
            this.trigger.focus();
        }
    }

    destroy() {
        if (this.destroyed) {
            return;
        }

        this.unbindOpenListeners();
        this.listenerRemovers.forEach(function (remove) { remove(); });
        this.listenerRemovers = [];
        this.panel.hidden = true;
        this.root.classList.remove("is-open", "lm-dropdown");
        this.root.removeAttribute("data-lemonade-dropdown-placement");
        this.trigger.setAttribute("aria-expanded", "false");
        delete this.root.lemonadeDropdown;
        this.destroyed = true;
    }

    isOpen() {
        return this.root.classList.contains("is-open");
    }

    handleTriggerClick(event) {
        event.preventDefault();
        event.stopPropagation();
        this.toggle();
    }

    bindOpenListeners() {
        const reposition = EventHelper.animationFrame(this.position.bind(this));

        this.openListenerRemovers.push(
            EventHelper.on(document, "click", this.handleDocumentClick.bind(this)),
            EventHelper.on(document, "keydown", this.handleDocumentKeydown.bind(this)),
        );

        if (this.fixed) {
            this.openListenerRemovers.push(
                EventHelper.on(window, "resize", reposition),
                EventHelper.on(document, "scroll", reposition, { capture: true }),
                reposition.cancel,
            );
        }
    }

    unbindOpenListeners() {
        this.openListenerRemovers.forEach(function (remove) { remove(); });
        this.openListenerRemovers = [];
    }

    handleDocumentClick(event) {
        if (!this.isInside(event)) {
            this.close("outside");
            return;
        }

        if (this.closeOnInsideClick && !this.trigger.contains(event.target)) {
            this.close("inside");
        }
    }

    isInside(event) {
        return this.containsEventTarget(this.root, event)
            || this.getRelatedRoots().some(function (root) {
                return this.containsEventTarget(root, event);
            }, this);
    }

    getRelatedRoots() {
        const roots = typeof this.relatedRoots === "function" ? this.relatedRoots() : this.relatedRoots;

        if (!roots) {
            return [];
        }

        return Array.isArray(roots) ? roots : [roots];
    }

    containsEventTarget(root, event) {
        if (!root) {
            return false;
        }

        return root.contains(event.target) || event.composedPath?.().includes(root);
    }

    handleDocumentKeydown(event) {
        if (event.key === "Escape") {
            event.preventDefault();
            this.close("escape");
        }
    }

    position() {
        if (!this.fixed || !this.isOpen()) {
            return;
        }

        const margin = 8;
        const triggerRect = this.trigger.getBoundingClientRect();
        const panelRect = this.panel.getBoundingClientRect();
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const left = Math.max(margin, Math.min(triggerRect.right - panelRect.width, viewportWidth - panelRect.width - margin));
        const top = Math.max(margin, Math.min(triggerRect.bottom, viewportHeight - panelRect.height - margin));

        this.panel.style.left = `${left}px`;
        this.panel.style.top = `${top}px`;
    }

    static create(root, options = {}) {
        root.lemonadeDropdown?.destroy();
        const dropdown = new Dropdown(root, options);

        if (!dropdown.init()) {
            return null;
        }

        root.lemonadeDropdown = dropdown;

        return dropdown;
    }

    static mount(root = document) {
        const elements = root.matches?.("[data-lemonade-dropdown]") ? [root] : [];
        elements.push(...DomHelper.queryAll("[data-lemonade-dropdown]", root));
        elements.forEach(function (element) {
            if (element.lemonadeDropdown) {
                return;
            }

            Dropdown.create(element, {
                placement: element.getAttribute("data-lemonade-dropdown-placement") || "bottom-end",
                closeOnInsideClick: element.getAttribute("data-lemonade-dropdown-close-on-inside") !== "false",
                returnFocus: element.getAttribute("data-lemonade-dropdown-return-focus") !== "false",
            });
        });
    }

    static destroy(root = document) {
        const elements = root.matches?.("[data-lemonade-dropdown]") ? [root] : [];
        elements.push(...DomHelper.queryAll("[data-lemonade-dropdown]", root));
        elements.forEach(function (element) { element.lemonadeDropdown?.destroy(); });
    }
}

export function mount(root = document) {
    Dropdown.mount(root);
}

export function destroy(root = document) {
    Dropdown.destroy(root);
}
