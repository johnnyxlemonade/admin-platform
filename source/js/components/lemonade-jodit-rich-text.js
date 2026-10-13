/*!
 * Lemonade Jodit Rich Text
 *
 * Progressively enhances Jodit-selected AdminEditor textareas without changing the Admin form contract.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { Jodit } from "jodit/esm/index.js";
import csCz from "jodit/esm/langs/cs_cz.js";
import "jodit/esm/plugins/all.js";
import { I18n } from "../core/lemonade-i18n.js";
import { imageTargetFor, RichTextImagePicker } from "./lemonade-rich-text-image-picker.js";
import "../../scss/components/_jodit-rich-text.scss";

const selector = "textarea[data-lemonade-admin-rich-text=jodit]";

function localeFor(textarea) {
    return textarea.closest("[data-lemonade-admin-shell]")?.getAttribute("data-lemonade-locale") === "cs" ? "cs_cz" : "en";
}

function themeFor(textarea) {
    return textarea.closest("[data-bs-theme]")?.getAttribute("data-bs-theme") === "dark" ? "dark" : "default";
}

function dispatchNativeChange(textarea) {
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
    textarea.dispatchEvent(new Event("change", { bubbles: true }));
}

function lemonadeImageControl(component) {
    return {
        icon: "image",
        tooltip: I18n.t("admin.rich_text.insert_image"),
        tags: ["img"],
        exec: component.handleImage,
    };
}

class JoditRichText {
    constructor(textarea) {
        this.textarea = textarea;
        this.handleChange = this.handleChange.bind(this);
        this.handleThemeChange = this.handleThemeChange.bind(this);
        this.handleImage = this.handleImage.bind(this);
    }

    mount() {
        this.imageTarget = imageTargetFor(this.textarea);
        this.editor = Jodit.make(this.textarea, {
            language: localeFor(this.textarea),
            i18n: { cs_cz: csCz },
            theme: themeFor(this.textarea),
            minHeight: 256,
            sourceEditor: "area",
            beautifyHTML: false,
            hidePoweredByJodit: true,
            toolbarAdaptive: true,
            controls: this.imageTarget ? {
                lemonadeImage: lemonadeImageControl(this),
            } : undefined,
            buttons: [
                "source",
                "|",
                "undo",
                "redo",
                "|",
                "paragraph",
                "|",
                "bold",
                "italic",
                "underline",
                "strikethrough",
                "|",
                "ul",
                "ol",
                "align",
                "|",
                "link",
                "unlink",
                "|",
                ...(this.imageTarget ? ["lemonadeImage", "|"] : []),
                "hr",
                "table",
                "|",
                "eraser",
                "|",
                "fullsize",
            ],
            buttonsMD: ["source", "undo", "redo", "paragraph", "bold", "italic", "underline", "strikethrough", "ul", "ol", "align", "link", "unlink", ...(this.imageTarget ? ["lemonadeImage"] : []), "hr", "table", "eraser", "fullsize", "dots"],
            buttonsSM: ["source", "undo", "redo", "paragraph", "bold", "italic", "underline", "strikethrough", "ul", "ol", "align", "link", ...(this.imageTarget ? ["lemonadeImage"] : []), "table", "eraser", "fullsize", "dots"],
            buttonsXS: ["source", "undo", "redo", "bold", "italic", "underline", "ul", "ol", "align", "link", ...(this.imageTarget ? ["lemonadeImage"] : []), "table", "eraser", "fullsize", "dots"],
        });
        this.canonicalValue = this.editor.value;
        this.editor.e.on("change", this.handleChange);
        this.themeObserver = new MutationObserver(this.handleThemeChange);
        this.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ["data-bs-theme"] });
    }

    handleChange() {
        const value = this.editor.value;
        if (value === this.canonicalValue) {
            return;
        }

        this.canonicalValue = value;
        dispatchNativeChange(this.textarea);
    }

    handleThemeChange() {
        this.editor?.setMod("theme", themeFor(this.textarea));
    }

    handleImage(editor, current) {
        if (!this.imageTarget) {
            return false;
        }

        const image = current instanceof HTMLImageElement
            ? current
            : current instanceof Element
                ? current.closest("img") || current.querySelector("img")
                : null;
        if (!this.imagePicker) {
            this.imagePicker = new RichTextImagePicker(this.imageTarget, editor.container, (state) => this.saveImage(state));
        }
        if (image instanceof HTMLImageElement && editor.editor.contains(image) && Number.isSafeInteger(Number(image.dataset.fileId)) && Number(image.dataset.fileId) > 0) {
            void this.imagePicker.openEdit(image);
        } else {
            editor.s.save();
            this.imagePicker.openInsert();
        }

        return false;
    }

    async saveImage({ image, selection, properties }) {
        const target = image instanceof HTMLImageElement ? image : this.insertImage();
        if (!target) {
            return false;
        }

        target.src = selection.originalUrl;
        target.setAttribute("data-file-id", String(selection.fileId));
        target.alt = properties.alt;
        if (properties.alignment === "default") {
            target.removeAttribute("data-align");
        } else {
            target.dataset.align = properties.alignment;
        }
        if (properties.width === "default") {
            target.removeAttribute("data-width");
        } else {
            target.dataset.width = properties.width;
        }
        this.editor.synchronizeValues();
        this.handleChange();

        return true;
    }

    insertImage() {
        this.editor.s.restore();
        const image = this.editor.createInside.element("img");
        this.editor.s.insertImage(image);

        return image;
    }

    destroy() {
        this.themeObserver?.disconnect();
        this.imagePicker?.destroy();
        this.imagePicker = null;
        this.editor?.e.off("change", this.handleChange);
        this.editor?.destruct();
        this.editor = null;
    }
}

export async function mount(root = document) {
    const textareas = root.matches?.(selector) ? [root] : [];
    textareas.push(...root.querySelectorAll?.(selector) || []);
    textareas.forEach((textarea) => {
        if (textarea.lemonadeJoditRichText) {
            return;
        }

        const component = new JoditRichText(textarea);
        textarea.lemonadeJoditRichText = component;
        component.mount();
    });
}

export function destroy(root = document) {
    const textareas = root.matches?.(selector) ? [root] : [];
    textareas.push(...root.querySelectorAll?.(selector) || []);
    textareas.forEach((textarea) => {
        textarea.lemonadeJoditRichText?.destroy();
        delete textarea.lemonadeJoditRichText;
    });
}
