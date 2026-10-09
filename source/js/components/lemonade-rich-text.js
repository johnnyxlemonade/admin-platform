/*!
 * Lemonade Rich Text
 *
 * Progressively enhances canonical AdminEditor textareas with Trumbowyg.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import $ from "jquery";
import iconSpriteUrl from "trumbowyg/dist/ui/icons.svg?url";

const selector = "textarea[data-lemonade-admin-rich-text]";
const toolbarStateButtons = ["p", "blockquote", "h2", "h3", "h4", "strong", "em", "underline", "del", "unorderedList", "orderedList", "link", "createLink", "unlink"];
let libraryPromise;

async function loadLibrary(locale) {
    if (!libraryPromise) {
        window.$ = $;
        window.jQuery = $;
        libraryPromise = Promise.all([
            import("trumbowyg/dist/trumbowyg.min.js"),
            import("trumbowyg/dist/plugins/table/trumbowyg.table.min.js"),
        ]);
    }

    await libraryPromise;
    if (locale === "cs") {
        await import("trumbowyg/dist/langs/cs.min.js");
    }
}

function localeFor(textarea) {
    return textarea.closest("[data-lemonade-admin-shell]")?.getAttribute("data-lemonade-locale") === "cs" ? "cs" : "en";
}

function normalizeEmptyHtml(html) {
    const documentFragment = document.createElement("div");
    documentFragment.innerHTML = html;
    if (documentFragment.textContent.trim() !== "") {
        return html;
    }

    return documentFragment.querySelector("hr, img, embed, iframe, input, figure, figcaption, audio, video, source, table, ul, ol, blockquote") ? html : "";
}

function dispatchNativeChange(textarea) {
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
    textarea.dispatchEvent(new Event("change", { bubbles: true }));
}

class RichText {
    constructor(textarea) {
        this.textarea = textarea;
        this.form = textarea.closest("form");
        this.handleChange = this.handleChange.bind(this);
        this.handleDiscard = this.handleDiscard.bind(this);
        this.handleLinkClick = this.handleLinkClick.bind(this);
        this.handleTextareaInput = this.handleTextareaInput.bind(this);
        this.handleLocaleChange = this.handleLocaleChange.bind(this);
        this.handleSelectionChange = this.handleSelectionChange.bind(this);
        this.handleViewHtmlToggle = this.handleViewHtmlToggle.bind(this);
    }

    async mount() {
        this.locale = localeFor(this.textarea);
        await loadLibrary(this.locale);
        const initial = normalizeEmptyHtml(this.textarea.value);
        this.textarea.value = initial;
        $(this.textarea).trumbowyg({
            lang: this.locale,
            svgPath: iconSpriteUrl,
            semantic: true,
            tagsToKeep: ["hr", "img", "embed", "iframe", "input", "figure", "figcaption", "audio", "video", "source", "table"],
            btns: [
                ["viewHTML"],
                ["undo", "redo"],
                ["formatting"],
                ["strong", "em", "underline", "del", "removeformat"],
                ["unorderedList", "orderedList"],
                ["link", "unlink"],
                ["horizontalRule"],
                ["table"],
                ["fullscreen"],
            ],
            btnsDef: {
                formatting: { dropdown: ["p", "blockquote", "h2", "h3", "h4"], ico: "p" },
            },
        });
        const trumbowyg = $(this.textarea).data("trumbowyg");
        this.box = trumbowyg.$box;
        this.box.toggleClass("is-invalid", this.textarea.classList.contains("is-invalid"));
        this.canonicalValue = normalizeEmptyHtml(this.textarea.value);
        this.textarea.value = this.canonicalValue;
        $(this.textarea).on("tbwchange", this.handleChange);
        this.textarea.addEventListener("input", this.handleTextareaInput);
        this.editor = trumbowyg.$ed;
        this.editorElement = this.editor.get(0);
        this.editorElement?.addEventListener("click", this.handleLinkClick, true);
        this.boxElement = this.box.get(0);
        this.boxElement?.addEventListener("mousedown", this.handleViewHtmlToggle, true);
        this.editor.on("mouseup keydown keyup focus", this.handleSelectionChange);
        this.updateToolbarState();
        this.form?.addEventListener("lemonade:dirty-state:discard", this.handleDiscard);
        document.addEventListener("lemonade:locale:changed", this.handleLocaleChange);
    }

    handleChange() {
        const value = normalizeEmptyHtml(this.textarea.value);
        if (this.viewHtmlTogglePending) {
            this.viewHtmlTogglePending = false;
            this.textarea.value = this.canonicalValue;

            return;
        }
        if (this.textarea.value !== value) {
            this.textarea.value = value;
        }
        if (value === this.canonicalValue) {
            return;
        }
        this.canonicalValue = value;
        this.isDispatchingNativeChange = true;
        dispatchNativeChange(this.textarea);
        this.isDispatchingNativeChange = false;
    }

    handleTextareaInput() {
        if (this.isDispatchingNativeChange) {
            return;
        }
        const value = normalizeEmptyHtml(this.textarea.value);
        if (this.textarea.value !== value) {
            this.textarea.value = value;
        }
        if (value === this.canonicalValue) {
            return;
        }
        this.canonicalValue = value;
        this.textarea.dispatchEvent(new Event("change", { bubbles: true }));
    }

    async handleLocaleChange() {
        if (this.isLocaleRemounting || this.locale === localeFor(this.textarea)) {
            return;
        }
        this.isLocaleRemounting = true;
        try {
            const value = this.canonicalValue;
            this.destroy();
            this.textarea.value = value;
            await this.mount();
        } finally {
            this.isLocaleRemounting = false;
        }
    }

    handleDiscard() {
        const value = normalizeEmptyHtml(this.textarea.value);
        this.textarea.value = value;
        this.canonicalValue = value;
        $(this.textarea).trumbowyg("html", value);
    }

    handleViewHtmlToggle(event) {
        if (event.target.closest?.(".trumbowyg-viewHTML-button")) {
            this.viewHtmlTogglePending = true;
        }
    }

    handleLinkClick(event) {
        const link = event.target.closest?.("a[href]") || event.target.parentElement?.closest?.("a[href]");
        const insideEditor = link !== undefined && this.editorElement?.contains(link) === true;
        if (!link || !insideEditor || event.ctrlKey || event.metaKey) {
            return;
        }
        event.preventDefault();
    }

    handleSelectionChange() {
        window.requestAnimationFrame(() => this.updateToolbarState());
    }

    updateToolbarState() {
        const editor = $(this.textarea).data("trumbowyg");
        if (!editor) {
            return;
        }
        editor.updateButtonPaneStatus();
        const toolbar = editor.$btnPane;
        const activeClass = "trumbowyg-active";
        toolbarStateButtons.forEach(function (buttonName) {
            toolbar.find(`.trumbowyg-${buttonName}-button`).attr("aria-pressed", "false");
            this.box.find(`.trumbowyg-${buttonName}-dropdown-button`).removeClass(activeClass).attr("aria-pressed", "false");
        }, this);
        editor.getTagsRecursive(editor.doc.getSelection().anchorNode).forEach(function (tag) {
            const buttonName = editor.tagToButton[tag.toLowerCase()];
            if (!buttonName) {
                return;
            }
            const directButton = toolbar.find(`.trumbowyg-${buttonName}-button`);
            const dropdownButton = this.box.find(`.trumbowyg-${buttonName}-dropdown-button`);
            directButton.add(dropdownButton).addClass(activeClass).attr("aria-pressed", "true");
        }, this);
        toolbar.find(".trumbowyg-active-button").attr("aria-pressed", "true");
    }

    destroy() {
        this.form?.removeEventListener("lemonade:dirty-state:discard", this.handleDiscard);
        document.removeEventListener("lemonade:locale:changed", this.handleLocaleChange);
        $(this.textarea).off("tbwchange", this.handleChange);
        this.textarea.removeEventListener("input", this.handleTextareaInput);
        this.editorElement?.removeEventListener("click", this.handleLinkClick, true);
        this.boxElement?.removeEventListener("mousedown", this.handleViewHtmlToggle, true);
        this.editor?.off("mouseup keydown keyup focus", this.handleSelectionChange);
        this.editorElement = null;
        this.boxElement = null;
        if ($(this.textarea).data("trumbowyg")) {
            $(this.textarea).trumbowyg("destroy");
        }
    }
}

export async function mount(root = document) {
    const textareas = root.matches?.(selector) ? [root] : [];
    textareas.push(...root.querySelectorAll?.(selector) || []);
    await Promise.all(textareas.map(async function (textarea) {
        if (textarea.lemonadeRichText) {
            return;
        }
        const component = new RichText(textarea);
        textarea.lemonadeRichText = component;
        await component.mount();
    }));
}

export function destroy(root = document) {
    const textareas = root.matches?.(selector) ? [root] : [];
    textareas.push(...root.querySelectorAll?.(selector) || []);
    textareas.forEach(function (textarea) {
        textarea.lemonadeRichText?.destroy();
        delete textarea.lemonadeRichText;
    });
}
