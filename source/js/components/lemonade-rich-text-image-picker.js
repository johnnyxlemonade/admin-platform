/*!
 * Lemonade Rich Text Image Picker
 *
 * Provides a canonical Admin image picker over the shared file collection and chunk upload contract.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { uploadFileChunks, uploadPreflightError, uploadValidationErrorMessage } from "./lemonade-file-upload.js";
import { Modal } from "./lemonade-modal.js";

function text(key) {
    return I18n.t(`admin.rich_text.${key}`);
}

function uploadUrl(template, uploadId) {
    return template.replace("__upload_id__", encodeURIComponent(uploadId));
}

export function imageTargetFor(textarea) {
    const serialized = textarea.dataset.lemonadeRichTextUploadTarget;
    if (!serialized) {
        return null;
    }

    try {
        const target = JSON.parse(serialized);
        if (typeof target?.module !== "string" || !Number.isSafeInteger(target.entity) || target.entity <= 0 || target.usage !== "content_image" || target.kind !== "image" || typeof target.collectionUrl !== "string" || typeof target.removeUrlTemplate !== "string" || typeof target.startUrl !== "string" || typeof target.appendUrlTemplate !== "string" || typeof target.completeUrlTemplate !== "string" || typeof target.abortUrlTemplate !== "string" || typeof target.accept !== "string" || !Array.isArray(target.allowedExtensions) || !Number.isSafeInteger(target.maxBytes) || target.maxBytes <= 0 || typeof target.maxSizeLabel !== "string") {
            return null;
        }

        return target;
    } catch {
        return null;
    }
}

/**
 * Keeps the generic rich-text image selection and properties states in one Admin modal.
 */
export class RichTextImagePicker {
    constructor(target, opener, onSave, onRemove, onDetach) {
        this.target = target;
        this.opener = opener;
        this.onSave = onSave;
        this.onRemove = onRemove;
        this.onDetach = onDetach;
        this.handleUpload = this.handleUpload.bind(this);
        this.uploadInput = document.createElement("input");
        this.uploadInput.type = "file";
        this.uploadInput.accept = target.accept;
        this.uploadInput.hidden = true;
        this.uploadInput.addEventListener("change", this.handleUpload);
        document.body.append(this.uploadInput);
    }

    openInsert() {
        this.editingImage = null;
        this.selection = null;
        this.properties = null;
        void this.renderSelection();
    }

    async openEdit(image) {
        const fileId = Number(image?.dataset.fileId);
        if (!(image instanceof HTMLImageElement) || !Number.isSafeInteger(fileId) || fileId <= 0) {
            return;
        }

        const selection = await this.selectionForFile(fileId);
        if (!selection) {
            return;
        }
        this.editingImage = image;
        this.selection = selection;
        this.properties = {
            alt: image.alt,
            alignment: this.validAlignment(image.dataset.align),
            width: this.validWidth(image.dataset.width),
        };
        this.renderProperties();
    }

    destroy() {
        this.abortUpload();
        this.uploadInput.removeEventListener("change", this.handleUpload);
        this.uploadInput.remove();
        this.modal?.destroy();
        this.modal = null;
    }

    ensureModal() {
        if (!this.modal) {
            this.modal = Modal.create({ size: "large", scrollable: true, kind: "rich-text-image-picker" });
        }

        return this.modal;
    }

    async renderSelection() {
        const modal = this.ensureModal();
        const content = document.createElement("div");
        const header = this.header(text("insert_image"));
        const body = document.createElement("section");
        body.className = "lm-modal-body";
        const upload = document.createElement("button");
        upload.type = "button";
        upload.className = "btn btn-primary lm-rich-text-upload-action";
        upload.textContent = text("upload_image");
        upload.addEventListener("click", () => this.uploadInput.click());
        const sectionTitle = document.createElement("h3");
        sectionTitle.className = "h6 lm-rich-text-upload-existing-title";
        sectionTitle.textContent = text("existing_images");
        this.items = document.createElement("div");
        this.items.className = "lm-rich-text-image-picker-items";
        body.append(upload, this.uploadRules(), sectionTitle, this.items);
        content.append(header, body);
        modal.setContent(content);
        if (!modal.isOpen) {
            modal.open({ opener: this.opener, initialFocus: () => upload });
        }

        try {
            await this.loadFiles();
        } catch {
            this.items.textContent = text("load_failed");
        }
    }

    async loadFiles() {
        if (!this.items) {
            return;
        }

        this.items.replaceChildren();
        this.items.setAttribute("aria-busy", "true");
        try {
            const response = await HttpHelper.get(this.target.collectionUrl);
            if (response?.success !== true || !Array.isArray(response.files)) {
                throw new Error("Image collection could not be loaded.");
            }

            const files = response.files.filter((file) => file?.kind === "image" && typeof file.preview_url === "string" && typeof file.original_url === "string");
            if (files.length === 0) {
                this.items.textContent = text("empty_images");
                return;
            }

            files.forEach((file) => this.items.append(this.tileFor(file)));
        } finally {
            this.items.removeAttribute("aria-busy");
        }
    }

    tileFor(file) {
        const tile = document.createElement("article");
        tile.className = "lm-rich-text-image-picker-tile";
        const select = document.createElement("button");
        select.type = "button";
        select.className = "lm-rich-text-image-picker-select";
        const preview = document.createElement("img");
        preview.className = "lm-rich-text-image-picker-preview";
        preview.src = file.preview_url;
        preview.alt = "";
        const name = document.createElement("span");
        name.className = "lm-rich-text-image-picker-name";
        name.textContent = file.display_name || file.original_filename || "";
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "lm-rich-text-image-picker-remove";
        remove.setAttribute("aria-label", text("delete_image"));
        remove.title = text("delete_image");
        remove.innerHTML = '<i class="bi bi-trash3" aria-hidden="true"></i>';
        remove.addEventListener("click", async (event) => {
            event.stopPropagation();
            remove.disabled = true;
            try {
                const response = await HttpHelper.post(uploadUrl(this.target.removeUrlTemplate, file.id));
                if (response?.success === true) {
                    this.onRemove(Number(file.id));
                    this.clearDeletedSelection(Number(file.id));
                    tile.remove();
                }
            } finally {
                remove.disabled = false;
            }
        });
        select.append(preview, name);
        tile.append(select, remove);
        select.addEventListener("click", () => {
            const fileId = Number(file.id);
            if (!Number.isSafeInteger(fileId) || fileId <= 0) {
                return;
            }

            this.selection = { fileId, previewUrl: file.preview_url, originalUrl: file.original_url };
            this.properties = this.defaultProperties();
            this.renderProperties();
        });

        return tile;
    }

    renderProperties() {
        if (!this.selection || !this.properties) {
            return;
        }

        const isEditing = this.editingImage instanceof HTMLImageElement;
        const modal = this.ensureModal();
        const content = document.createElement("div");
        const header = this.header(text(isEditing ? "edit_image" : "insert_image"));
        const body = document.createElement("section");
        body.className = "lm-modal-body";
        const preview = document.createElement("img");
        preview.className = "lm-rich-text-image-properties-preview";
        preview.src = this.selection.previewUrl;
        preview.alt = "";
        const alt = document.createElement("input");
        alt.className = "form-control";
        alt.value = this.properties.alt;
        const alignment = this.select("alignment", ["default", "left", "center", "right"], this.properties.alignment);
        const width = this.select("width", ["default", "25", "50", "75", "100"], this.properties.width);
        body.append(preview, this.field(text("alternative_text"), alt), this.field(text("alignment"), alignment), this.field(text("width"), width));
        const footer = document.createElement("footer");
        footer.className = "lm-modal-footer";
        if (isEditing) {
            const detach = document.createElement("button");
            detach.type = "button";
            detach.className = "btn btn-outline-danger me-auto";
            detach.textContent = text("remove_from_content");
            detach.addEventListener("click", () => {
                if (this.onDetach(this.editingImage) !== false) {
                    this.clearDetachedSelection();
                    modal.close({ reason: "detached" });
                }
            });
            footer.append(detach);
        }
        const back = document.createElement("button");
        back.type = "button";
        back.className = "btn btn-light";
        back.textContent = text(isEditing ? "change_image" : "back");
        back.addEventListener("click", () => {
            this.editingImage = null;
            void this.renderSelection();
        });
        const save = document.createElement("button");
        save.type = "button";
        save.className = "btn btn-primary";
        save.textContent = text(isEditing ? "save_image" : "insert_image");
        save.addEventListener("click", async () => {
            save.disabled = true;
            try {
                const saved = await this.onSave({
                    image: this.editingImage,
                    selection: this.selection,
                    properties: {
                        alt: alt.value,
                        alignment: alignment.value,
                        width: width.value,
                    },
                });
                if (saved !== false) {
                    modal.close({ reason: "saved" });
                }
            } finally {
                save.disabled = false;
            }
        });
        footer.append(back, save);
        content.append(header, body, footer);
        modal.setContent(content);
        if (!modal.isOpen) {
            modal.open({ opener: this.opener, initialFocus: () => alt });
        }
    }

    header(titleText) {
        const header = document.createElement("header");
        header.className = "lm-modal-header";
        const title = document.createElement("h2");
        title.className = "lm-modal-title";
        title.setAttribute("data-lemonade-modal-title", "");
        title.textContent = titleText;
        const close = document.createElement("button");
        close.type = "button";
        close.className = "lm-modal-close";
        close.setAttribute("data-lemonade-modal-close", "");
        close.setAttribute("aria-label", text("close"));
        header.append(title, close);

        return header;
    }

    field(labelText, control) {
        const field = document.createElement("div");
        field.className = "lm-form-group mb-3";
        const label = document.createElement("label");
        label.className = "lm-form-label";
        label.textContent = labelText;
        field.append(label, control);

        return field;
    }

    select(name, values, selected) {
        const control = document.createElement("select");
        control.className = "form-select";
        values.forEach((value) => control.add(new Option(text(`${name}_${value}`), value, false, value === selected)));

        return control;
    }

    async handleUpload() {
        const file = this.uploadInput.files?.[0];
        this.uploadInput.value = "";
        if (!file || this.uploadController) {
            return;
        }
        const preflightError = uploadPreflightError(file, this.target);
        if (preflightError) {
            this.showUploadError(preflightError);
            return;
        }

        this.uploadController = new AbortController();
        try {
            const completed = await uploadFileChunks(file, this.target, {
                signal: this.uploadController.signal,
                onStarted: (uploadId) => {
                    this.uploadId = uploadId;
                },
            });
            const fileId = Number(completed?.fileId);
            const uploadedFile = completed?.files?.find((entry) => Number(entry?.id) === fileId);
            if (!Number.isSafeInteger(fileId) || fileId <= 0 || typeof uploadedFile?.preview_url !== "string" || typeof uploadedFile?.original_url !== "string") {
                throw new Error("Image upload completion is invalid.");
            }

            this.selection = { fileId, previewUrl: uploadedFile.preview_url, originalUrl: uploadedFile.original_url };
            this.properties = this.defaultProperties();
            this.renderProperties();
        } catch (error) {
            if (!this.uploadController.signal.aborted) {
                console.error("Rich-text image upload failed.", error);
                this.showUploadError(uploadValidationErrorMessage(error, this.target, text("invalid_file"), text("upload_failed")));
            }
        } finally {
            this.uploadController = null;
            this.uploadId = null;
        }
    }

    abortUpload() {
        if (!this.uploadController) {
            return;
        }

        this.uploadController.abort();
        if (this.uploadId) {
            void HttpHelper.post(uploadUrl(this.target.abortUrlTemplate, this.uploadId)).catch(() => undefined);
        }
    }

    defaultProperties() {
        return { alt: "", alignment: "default", width: "default" };
    }

    validAlignment(value) {
        return ["left", "center", "right"].includes(value) ? value : "default";
    }

    validWidth(value) {
        return ["25", "50", "75", "100"].includes(value) ? value : "default";
    }

    async selectionForFile(fileId) {
        const response = await HttpHelper.get(this.target.collectionUrl);
        if (response?.success !== true || !Array.isArray(response.files)) {
            return null;
        }

        const file = response.files.find((entry) => Number(entry?.id) === fileId && entry?.kind === "image" && typeof entry.preview_url === "string" && typeof entry.original_url === "string");
        if (!file) {
            return null;
        }

        return { fileId, previewUrl: file.preview_url, originalUrl: file.original_url };
    }

    clearDeletedSelection(fileId) {
        if (this.selection?.fileId !== fileId) {
            return;
        }

        this.editingImage = null;
        this.selection = null;
        this.properties = null;
    }

    clearDetachedSelection() {
        this.editingImage = null;
        this.selection = null;
        this.properties = null;
    }

    uploadRules() {
        const rules = document.createElement("div");
        rules.className = "lm-file-upload-rules lm-rich-text-upload-rules";
        const title = document.createElement("span");
        title.className = "lm-file-upload-rules-title";
        title.textContent = I18n.t("admin.file_upload.allowed_types");
        const extensions = document.createElement("div");
        extensions.className = "lm-file-upload-chips";
        this.target.allowedExtensions.forEach((extension) => {
            const chip = document.createElement("span");
            chip.className = "lm-file-upload-chip";
            chip.textContent = extension;
            extensions.append(chip);
        });
        const maximum = document.createElement("small");
        maximum.textContent = I18n.t("admin.file_upload.max_size", { size: this.target.maxSizeLabel });
        rules.append(title, extensions, maximum);

        return rules;
    }

    showUploadError(message) {
        if (this.items) {
            this.items.textContent = message;
        }
    }
}
