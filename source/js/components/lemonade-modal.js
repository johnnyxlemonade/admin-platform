/*!
 * Lemonade Modal
 *
 * Provides the native dialog presentation lifecycle for Admin overlays.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";

const SIZES = ["small", "medium", "large", "full"];
let generatedId = 0;

export class Modal {
    static scrollLockCount = 0;

    static bodyScrollState = null;

    static create(options = {}) {
        return new this(options);
    }

    constructor(options = {}) {
        this.size = SIZES.includes(options.size) ? options.size : "medium";
        this.centered = options.centered === true;
        this.scrollable = options.scrollable === true;
        this.kind = options.kind || "";
        this.opener = null;
        this.closeReason = "close";
        this.isOpen = false;
        this.element = document.createElement("dialog");
        this.element.className = "lm-modal";
        this.element.setAttribute("data-lemonade-modal-root", "");
        this.element.innerHTML = '<div class="lm-modal-dialog" data-lemonade-modal-surface></div><div data-lemonade-modal-floating-root></div>';
        document.body.appendChild(this.element);

        this.surface = this.element.querySelector("[data-lemonade-modal-surface]");
        this.floatingRoot = this.element.querySelector("[data-lemonade-modal-floating-root]");
        this.configure();
        this.bindEvents();
    }

    configure(options = {}) {
        if (SIZES.includes(options.size)) {
            this.size = options.size;
        }
        if (typeof options.centered === "boolean") {
            this.centered = options.centered;
        }
        if (typeof options.scrollable === "boolean") {
            this.scrollable = options.scrollable;
        }
        if (typeof options.kind === "string") {
            this.kind = options.kind;
        }

        this.element.setAttribute("data-lemonade-modal-size", this.size);
        this.element.toggleAttribute("data-lemonade-modal-centered", this.centered);
        this.element.toggleAttribute("data-lemonade-modal-scrollable", this.scrollable);
        if (this.kind) {
            this.element.setAttribute("data-lemonade-modal-kind", this.kind);
        } else {
            this.element.removeAttribute("data-lemonade-modal-kind");
        }
    }

    setContent(content) {
        this.surface.replaceChildren();
        if (typeof content === "string") {
            this.surface.innerHTML = content;
        } else if (content instanceof window.Node) {
            this.surface.appendChild(content);
        }

        const title = this.surface.querySelector("[data-lemonade-modal-title]");
        const description = this.surface.querySelector("[data-lemonade-modal-description]");
        if (title) {
            if (!title.id) {
                title.id = `lemonade-modal-title-${++generatedId}`;
            }
            this.element.setAttribute("aria-labelledby", title.id);
        } else {
            this.element.removeAttribute("aria-labelledby");
        }
        if (description) {
            if (!description.id) {
                description.id = `lemonade-modal-description-${++generatedId}`;
            }
            this.element.setAttribute("aria-describedby", description.id);
        } else {
            this.element.removeAttribute("aria-describedby");
        }
    }

    content() {
        return this.surface;
    }

    open(options = {}) {
        if (this.isOpen) {
            return;
        }

        this.configure(options);
        this.opener = options.opener || document.activeElement;
        this.initialFocus = typeof options.initialFocus === "function" ? options.initialFocus : null;
        this.closeReason = "close";
        this.lockBodyScroll();
        this.element.showModal();
        this.isOpen = true;
        window.requestAnimationFrame(() => this.focusInitial());
    }

    close(options = {}) {
        if (!this.element.open) {
            return;
        }

        this.closeReason = options.reason || "close";
        this.element.close();
    }

    destroy() {
        if (this.element.open) {
            this.close();
        }
        this.element.remove();
    }

    bindEvents() {
        EventHelper.on(this.element, "cancel", (event) => {
            event.preventDefault();
            this.close({ reason: "escape" });
        });
        EventHelper.on(this.element, "click", (event) => {
            const close = DomHelper.closest(event.target, "[data-lemonade-modal-close]");
            if (close) {
                this.close({ reason: close.getAttribute("data-lemonade-modal-close") || "close" });
                return;
            }
            if (event.target === this.element) {
                this.playBackdropFeedback();
            }
        });
        EventHelper.on(this.surface, "transitionend", (event) => {
            if (event.target === this.surface && event.propertyName === "translate" && this.surface.classList.contains("is-backdrop-feedback")) {
                this.surface.classList.remove("is-backdrop-feedback");
            }
        });
        EventHelper.on(this.element, "close", () => this.handleClosed());
    }

    playBackdropFeedback() {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        this.surface.classList.remove("is-backdrop-feedback");
        void this.surface.offsetWidth;
        this.surface.classList.add("is-backdrop-feedback");
    }

    focusInitial() {
        if (!this.element.open) {
            return;
        }

        const target = this.initialFocus ? this.initialFocus() : null;
        if (target && typeof target.focus === "function") {
            target.focus({ preventScroll: true });
        }
    }

    handleClosed() {
        if (!this.isOpen) {
            return;
        }

        this.isOpen = false;
        this.unlockBodyScroll();
        DomHelper.restoreFocusOutside(this.element, this.opener);
        this.element.dispatchEvent(new CustomEvent("lemonade:modal:closed", {
            bubbles: true,
            detail: { reason: this.closeReason },
        }));
        this.opener = null;
        this.initialFocus = null;
    }

    lockBodyScroll() {
        if (Modal.scrollLockCount++ !== 0) {
            return;
        }

        const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
        Modal.bodyScrollState = {
            overflow: document.body.style.overflow,
            paddingRight: document.body.style.paddingRight,
        };
        document.body.style.overflow = "hidden";
        if (scrollbarWidth > 0) {
            const paddingRight = Number.parseFloat(window.getComputedStyle(document.body).paddingRight) || 0;
            document.body.style.paddingRight = `${paddingRight + scrollbarWidth}px`;
        }
    }

    unlockBodyScroll() {
        if (Modal.scrollLockCount === 0 || --Modal.scrollLockCount !== 0) {
            return;
        }

        const state = Modal.bodyScrollState;
        if (state) {
            document.body.style.overflow = state.overflow;
            document.body.style.paddingRight = state.paddingRight;
        }
        Modal.bodyScrollState = null;
    }
}
