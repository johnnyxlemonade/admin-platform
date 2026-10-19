/*!
 * Lemonade URL Segment
 *
 * Normalizes editable CMS URL segments while keeping server routing authoritative.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";

const replacements = {
    "æ": "ae", "œ": "oe", "ø": "o", "ß": "ss", "þ": "th", "ð": "d",
    "ł": "l", "đ": "d", "ı": "i"
};

export function normalizeUrlSegment(value, maximumLength = 180) {
    const normalized = typeof String.prototype.normalize === "function"
        ? String(value || "").normalize("NFKD")
        : String(value || "");
    const transliterated = Array.from(normalized.toLowerCase()).map(function (character) {
        return replacements[character] || character;
    }).join("");
    const slug = transliterated
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/_/g, " ")
        .replace(/[^a-z0-9\s-]/g, "")
        .trim()
        .replace(/[\s-]+/g, "-");

    return slug.slice(0, Math.max(0, maximumLength)).replace(/-+$/g, "");
}

export class UrlSegment {
    constructor(input) {
        this.input = input;
        this.root = input.closest("[data-lemonade-url-segment-root]");
        this.form = input.form;
        this.composing = false;
        this.manual = !input.hasAttribute("data-lemonade-url-segment-auto");
        this.source = this.findSource();
        this.handleInput = this.handleInput.bind(this);
        this.handleChange = this.handleChange.bind(this);
        this.handlePaste = this.handlePaste.bind(this);
        this.handleCompositionStart = () => { this.composing = true; };
        this.handleCompositionEnd = () => {
            this.composing = false;
            this.normalizeInput(true);
        };
        this.handleSourceInput = this.handleSourceInput.bind(this);
        this.handleActionSuccess = this.handleActionSuccess.bind(this);
        this.input.addEventListener("input", this.handleInput);
        this.input.addEventListener("change", this.handleChange);
        this.input.addEventListener("paste", this.handlePaste);
        this.input.addEventListener("compositionstart", this.handleCompositionStart);
        this.input.addEventListener("compositionend", this.handleCompositionEnd);
        this.source?.addEventListener("input", this.handleSourceInput);
        this.root?.addEventListener("lemonade:action:success", this.handleActionSuccess);

        if (!this.manual) {
            this.applySource();
        } else {
            this.normalizeInput(false);
        }
    }

    destroy() {
        this.input.removeEventListener("input", this.handleInput);
        this.input.removeEventListener("change", this.handleChange);
        this.input.removeEventListener("paste", this.handlePaste);
        this.input.removeEventListener("compositionstart", this.handleCompositionStart);
        this.input.removeEventListener("compositionend", this.handleCompositionEnd);
        this.source?.removeEventListener("input", this.handleSourceInput);
        this.root?.removeEventListener("lemonade:action:success", this.handleActionSuccess);
        UrlSegment.instances.delete(this.input);
    }

    findSource() {
        const fieldName = this.input.getAttribute("data-lemonade-url-segment-source");

        return fieldName && this.form
            ? this.form.querySelector(`[data-lemonade-field="${CSS.escape(fieldName)}"]`)
            : null;
    }

    maximumLength() {
        return this.input.maxLength > 0 ? this.input.maxLength : 180;
    }

    handleInput() {
        if (this.composing) {
            return;
        }
        this.normalizeInput(true);
    }

    handleChange() {
        this.normalizeInput(true);
    }

    handlePaste() {
        window.setTimeout(() => this.normalizeInput(true), 0);
    }

    normalizeInput(manual) {
        if (manual) {
            this.manual = true;
        }
        this.input.value = normalizeUrlSegment(this.input.value, this.maximumLength());
    }

    handleSourceInput() {
        if (!this.manual) {
            this.applySource();
        }
    }

    applySource() {
        this.input.value = normalizeUrlSegment(this.source?.value || "", this.maximumLength());
    }

    handleActionSuccess(event) {
        if (!event.target?.hasAttribute("data-lemonade-url-segment-submit")) {
            return;
        }
        const segment = event.detail?.response?.data?.urlSegment;
        if (typeof segment === "string") {
            this.input.value = segment;
            this.manual = true;
        }
    }

    static mount(root = document) {
        const inputs = root.matches?.("[data-lemonade-url-segment]")
            ? [root]
            : DomHelper.queryAll("[data-lemonade-url-segment]", root);
        inputs.forEach(function (input) {
            if (!this.instances.has(input)) {
                this.instances.set(input, new this(input));
            }
        }, this);
    }

    static destroy(root = document) {
        const inputs = root.matches?.("[data-lemonade-url-segment]")
            ? [root]
            : DomHelper.queryAll("[data-lemonade-url-segment]", root);
        inputs.forEach((input) => this.instances.get(input)?.destroy());
    }
}

UrlSegment.instances = new Map();

export function mount(root = document) {
    UrlSegment.mount(root);
}

export function destroy(root = document) {
    UrlSegment.destroy(root);
}
