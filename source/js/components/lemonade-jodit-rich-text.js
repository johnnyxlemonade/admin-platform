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
import "jodit/esm/plugins/clean-html/clean-html.js";
import "jodit/esm/plugins/fullsize/fullsize.js";
import "jodit/esm/plugins/hr/hr.js";
import "jodit/esm/plugins/justify/justify.js";
import "jodit/esm/plugins/mobile/mobile.js";
import "jodit/esm/plugins/source/source.js";
import { I18n } from "../core/lemonade-i18n.js";
import { uploadFileChunks, uploadPreflightError, uploadValidationErrorMessage } from "./lemonade-file-upload.js";
import { imageTargetFor, RichTextImagePicker } from "./lemonade-rich-text-image-picker.js";
import { fileTargetFor, RichTextFilePicker } from "./lemonade-rich-text-file-picker.js";
import { Message } from "./lemonade-message.js";
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

function lemonadeFileControl(component) {
    return {
        icon: "file",
        tooltip: I18n.t("admin.rich_text.insert_file"),
        tags: ["a"],
        exec: component.handleFile,
    };
}

class JoditRichText {
    constructor(textarea) {
        this.textarea = textarea;
        this.handleChange = this.handleChange.bind(this);
        this.handleThemeChange = this.handleThemeChange.bind(this);
        this.handleImage = this.handleImage.bind(this);
        this.handleFile = this.handleFile.bind(this);
        this.handleManagedImageInteraction = this.handleManagedImageInteraction.bind(this);
        this.preventManagedImageSelection = this.preventManagedImageSelection.bind(this);
        this.handleAttachmentClick = this.handleAttachmentClick.bind(this);
        this.handleBeforeLinkPopup = this.handleBeforeLinkPopup.bind(this);
        this.handlePaste = this.handlePaste.bind(this);
    }

    mount() {
        this.imageTarget = imageTargetFor(this.textarea);
        this.fileTarget = fileTargetFor(this.textarea);
        this.editor = Jodit.make(this.textarea, {
            language: localeFor(this.textarea),
            i18n: { cs_cz: csCz },
            theme: themeFor(this.textarea),
            minHeight: 400,
            sourceEditor: "area",
            beautifyHTML: false,
            cleanHTML: {
                collapseEmptyValueToEmptyString: true,
            },
            hidePoweredByJodit: true,
            toolbarAdaptive: true,
            controls: this.controls(),
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
                ...(this.fileTarget ? ["lemonadeFile", "|"] : []),
                "hr",
                "table",
                "|",
                "eraser",
                "|",
                "fullsize",
            ],
            buttonsMD: ["source", "undo", "redo", "paragraph", "bold", "italic", "underline", "strikethrough", "ul", "ol", "align", "link", "unlink", ...(this.imageTarget ? ["lemonadeImage"] : []), ...(this.fileTarget ? ["lemonadeFile"] : []), "hr", "table", "eraser", "fullsize", "dots"],
            buttonsSM: ["source", "undo", "redo", "paragraph", "bold", "italic", "underline", "strikethrough", "ul", "ol", "align", "link", ...(this.imageTarget ? ["lemonadeImage"] : []), ...(this.fileTarget ? ["lemonadeFile"] : []), "table", "eraser", "fullsize", "dots"],
            buttonsXS: ["source", "undo", "redo", "bold", "italic", "underline", "ul", "ol", "align", "link", ...(this.imageTarget ? ["lemonadeImage"] : []), ...(this.fileTarget ? ["lemonadeFile"] : []), "table", "eraser", "fullsize", "dots"],
        });
        this.canonicalValue = this.textarea.value;
        this.editor.e.on("change", this.handleChange);
        this.editor.editor.addEventListener("paste", this.handlePaste, true);
        if (this.imageTarget) {
            this.editor.editor.addEventListener("mousedown", this.preventManagedImageSelection, true);
            this.editor.editor.addEventListener("click", this.handleManagedImageInteraction, true);
            this.editor.editor.addEventListener("dblclick", this.handleManagedImageInteraction, true);
        }
        if (this.fileTarget) {
            this.editor.e.on("before-link-open-popup", this.handleBeforeLinkPopup);
            this.editor.editor.addEventListener("click", this.handleAttachmentClick, true);
        }
        this.themeObserver = new MutationObserver(this.handleThemeChange);
        this.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ["data-bs-theme"] });
    }

    handleChange() {
        const value = this.editor.value;
        if (value === this.canonicalValue) {
            return;
        }

        this.canonicalValue = value;
        if (this.textarea.value !== value) {
            this.textarea.value = value;
        }
        dispatchNativeChange(this.textarea);
    }

    handleThemeChange() {
        this.editor?.setMod("theme", themeFor(this.textarea));
    }

    handleImage(editor, current) {
        if (!this.imageTarget) {
            return false;
        }

        const image = this.managedImageFor(current);
        if (image) {
            this.openImageEditor(image);
        } else {
            editor.s.save();
            this.imagePickerFor(editor).openInsert();
        }

        return false;
    }

    handleFile(editor, current) {
        if (!this.fileTarget) {
            return false;
        }

        const link = this.attachmentLinkFor(current);
        if (link) {
            this.openFileEditor(link);
        } else {
            editor.s.save();
            this.filePickerFor(editor).openInsert();
        }

        return false;
    }

    saveFile({ link, selection, label }) {
        const target = link instanceof HTMLAnchorElement ? link : this.insertFile();
        if (!target) return false;
        target.href = selection.publicUrl;
        target.dataset.fileId = String(selection.fileId);
        target.textContent = label;
        this.editor.synchronizeValues();
        this.handleChange();
        return true;
    }

    insertFile() {
        this.editor.s.restore();
        const link = this.editor.createInside.element("a");
        this.editor.s.insertNode(link);
        return link;
    }

    preventManagedImageSelection(event) {
        if (!this.managedImageFor(event.target)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
    }

    handleManagedImageInteraction(event) {
        const image = this.managedImageFor(event.target);
        if (!image) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        this.openImageEditor(image);
    }

    handleAttachmentClick(event) {
        const link = this.attachmentLinkFor(event.target);
        if (!link) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        this.openFileEditor(link);
    }

    handleBeforeLinkPopup(target) {
        return this.attachmentLinkFor(target) ? false : undefined;
    }

    handlePaste(event) {
        const image = this.clipboardImageFor(event.clipboardData);
        if (!image && !this.clipboardHasDataImage(event.clipboardData)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        if (!image || !this.imageTarget) {
            return;
        }

        this.editor.s.save();
        void this.uploadClipboardImage(image);
    }

    clipboardImageFor(clipboard) {
        const item = Array.from(clipboard?.items || []).find((candidate) => candidate.kind === "file" && candidate.type?.startsWith("image/"));
        const image = item?.getAsFile?.() || Array.from(clipboard?.files || []).find((candidate) => candidate.type?.startsWith("image/"));

        return image instanceof Blob && image.type.startsWith("image/") ? image : null;
    }

    clipboardHasDataImage(clipboard) {
        return /<img\b[^>]*\bsrc\s*=\s*["']?\s*data:image\//i.test(clipboard?.getData?.("text/html") || "");
    }

    async uploadClipboardImage(image) {
        const file = this.clipboardImageFile(image);
        const preflightError = uploadPreflightError(file, this.imageTarget);
        if (preflightError) {
            Message.error(preflightError);
            return;
        }

        try {
            const completed = await uploadFileChunks(file, this.imageTarget);
            const fileId = Number(completed?.fileId);
            const uploaded = completed?.files?.find((file) => Number(file?.id) === fileId);
            if (!Number.isSafeInteger(fileId) || fileId <= 0 || typeof uploaded?.original_url !== "string") {
                throw new Error("Clipboard image upload response is invalid.");
            }

            await this.saveImage({
                image: null,
                selection: { fileId, originalUrl: uploaded.original_url },
                properties: { alt: "", alignment: "default", width: "default" },
            });
        } catch (error) {
            console.error("Rich-text clipboard image upload failed.", error);
            Message.error(uploadValidationErrorMessage(
                error,
                this.imageTarget,
                I18n.t("admin.rich_text.invalid_file"),
                I18n.t("admin.rich_text.upload_failed"),
            ));
        }
    }

    clipboardImageFile(image) {
        const subtype = image.type.slice("image/".length).split("+", 1)[0];
        const extension = /^[a-z0-9]+$/i.test(subtype) ? subtype : "png";

        return new File([image], `clipboard-${Date.now()}.${extension}`, { type: image.type });
    }

    openFileEditor(link) {
        void this.filePickerFor(this.editor).openEdit(link);
    }

    openImageEditor(image) {
        void this.imagePickerFor(this.editor).openEdit(image);
    }

    imagePickerFor(editor) {
        if (!this.imagePicker) {
            this.imagePicker = new RichTextImagePicker(
                this.imageTarget,
                editor.container,
                (state) => this.saveImage(state),
                (fileId) => this.removeImageReferences(fileId),
                (image) => this.removeImageElement(image),
            );
        }

        return this.imagePicker;
    }

    filePickerFor(editor) {
        if (!this.filePicker) {
            this.filePicker = new RichTextFilePicker(
                this.fileTarget,
                editor.container,
                (state) => this.saveFile(state),
                (fileId) => this.removeFileReferences(fileId),
                (link) => this.removeFileElement(link),
            );
        }

        return this.filePicker;
    }

    attachmentLinkFor(target) {
        const element = target instanceof Element ? target : target?.parentElement;
        const link = element?.closest("a[data-file-id]");
        const fileId = Number(link?.dataset.fileId);

        return link instanceof HTMLAnchorElement
            && this.editor.editor.contains(link)
            && Number.isSafeInteger(fileId)
            && fileId > 0
            ? link
            : null;
    }

    managedImageFor(target) {
        const element = target instanceof Element ? target : target?.parentElement;
        const image = element instanceof HTMLImageElement
            ? element
            : element?.closest("img[data-file-id]");
        const fileId = Number(image?.dataset.fileId);

        return image instanceof HTMLImageElement
            && this.editor.editor.contains(image)
            && Number.isSafeInteger(fileId)
            && fileId > 0
            ? image
            : null;
    }

    controls() {
        if (!this.imageTarget && !this.fileTarget) {
            return undefined;
        }

        return {
            ...(this.imageTarget ? { lemonadeImage: lemonadeImageControl(this) } : {}),
            ...(this.fileTarget ? { lemonadeFile: lemonadeFileControl(this) } : {}),
        };
    }

    removeImageReferences(fileId) {
        this.removeReferences('img[data-file-id="' + fileId + '"]');
    }

    removeFileReferences(fileId) {
        this.removeReferences('a[data-file-id="' + fileId + '"]');
    }

    removeImageElement(image) {
        return this.removeEditorElement(this.managedImageFor(image));
    }

    removeFileElement(link) {
        return this.removeEditorElement(this.attachmentLinkFor(link));
    }

    removeEditorElement(element) {
        if (!(element instanceof Element) || !this.editor.editor.contains(element)) {
            return false;
        }

        element.remove();
        this.editor.synchronizeValues();
        this.handleChange();

        return true;
    }

    removeReferences(selector) {
        this.editor.editor.querySelectorAll(selector).forEach((reference) => reference.remove());
        this.editor.synchronizeValues();
        this.handleChange();
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
        this.filePicker?.destroy();
        this.imagePicker = null;
        if (this.imageTarget) {
            this.editor?.editor.removeEventListener("mousedown", this.preventManagedImageSelection, true);
            this.editor?.editor.removeEventListener("click", this.handleManagedImageInteraction, true);
            this.editor?.editor.removeEventListener("dblclick", this.handleManagedImageInteraction, true);
        }
        if (this.fileTarget) {
            this.editor?.e.off("before-link-open-popup", this.handleBeforeLinkPopup);
            this.editor?.editor.removeEventListener("click", this.handleAttachmentClick, true);
        }
        this.editor?.editor.removeEventListener("paste", this.handlePaste, true);
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
