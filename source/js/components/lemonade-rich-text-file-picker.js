/*!
 * Lemonade Rich Text File Picker
 *
 * Provides a canonical Admin file picker over the shared file collection and chunk upload contract.
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

export function fileTargetFor(textarea) {
    const serialized = textarea.dataset.lemonadeRichTextFileUploadTarget;
    if (!serialized) {
        return null;
    }

    try {
        const target = JSON.parse(serialized);
        if (typeof target?.module !== "string" || !Number.isSafeInteger(target.entity) || target.entity <= 0 || target.usage !== "content_file" || target.kind !== "file" || typeof target.collectionUrl !== "string" || typeof target.removeUrlTemplate !== "string" || typeof target.startUrl !== "string" || typeof target.appendUrlTemplate !== "string" || typeof target.completeUrlTemplate !== "string" || typeof target.abortUrlTemplate !== "string" || typeof target.publicDownloadUrlTemplate !== "string" || typeof target.accept !== "string" || !Array.isArray(target.allowedExtensions) || !Number.isSafeInteger(target.maxBytes) || target.maxBytes <= 0 || typeof target.maxSizeLabel !== "string") {
            return null;
        }

        return target;
    } catch {
        return null;
    }
}

/**
 * Keeps rich-text file selection and link properties states in one Admin modal.
 */
export class RichTextFilePicker {
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
        this.editingLink = null;
        this.selection = null;
        void this.renderSelection();
    }

    async openEdit(link) {
        const fileId = Number(link?.dataset.fileId);
        if (!(link instanceof HTMLAnchorElement) || !Number.isSafeInteger(fileId) || fileId <= 0) {
            return;
        }

        const selection = await this.selectionForFile(fileId);
        if (!selection) {
            return;
        }

        this.editingLink = link;
        this.selection = selection;
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
            this.modal = Modal.create({ size: "large", scrollable: true, kind: "rich-text-file-picker" });
        }

        return this.modal;
    }

    async renderSelection() {
        const modal = this.ensureModal();
        const content = document.createElement("div");
        const body = document.createElement("section");
        body.className = "lm-modal-body";
        const upload = document.createElement("button");
        upload.type = "button";
        upload.className = "btn btn-primary lm-rich-text-upload-action";
        upload.textContent = text("upload_file");
        upload.addEventListener("click", () => this.uploadInput.click());
        const sectionTitle = document.createElement("h3");
        sectionTitle.className = "h6 lm-rich-text-upload-existing-title";
        sectionTitle.textContent = text("existing_files");
        this.items = document.createElement("div");
        this.items.className = "lm-rich-text-file-picker-items";
        body.append(upload, this.uploadRules(), sectionTitle, this.items);
        content.append(this.header(text("insert_file")), body);
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
                throw new Error("File collection could not be loaded.");
            }

            const files = response.files.filter((file) => this.selectionFor(file) !== null);
            if (files.length === 0) {
                this.items.textContent = text("empty_files");
                return;
            }

            files.forEach((file) => this.items.append(this.rowFor(file)));
        } finally {
            this.items.removeAttribute("aria-busy");
        }
    }

    rowFor(file) {
        const row = document.createElement("article");
        row.className = "lm-rich-text-file-picker-row";
        const select = document.createElement("button");
        select.type = "button";
        select.className = "lm-rich-text-file-picker-select";
        select.textContent = file.display_name || file.original_filename || "";
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "lm-rich-text-file-picker-remove";
        remove.setAttribute("aria-label", text("delete_file"));
        remove.title = text("delete_file");
        remove.innerHTML = '<i class="bi bi-trash3" aria-hidden="true"></i>';
        remove.addEventListener("click", async (event) => {
            event.stopPropagation();
            remove.disabled = true;
            try {
                const response = await HttpHelper.post(uploadUrl(this.target.removeUrlTemplate, file.id));
                if (response?.success === true) {
                    this.onRemove(Number(file.id));
                    this.clearDeletedSelection(Number(file.id));
                    row.remove();
                    if (this.items?.childElementCount === 0) {
                        this.items.textContent = text("empty_files");
                    }
                }
            } finally {
                remove.disabled = false;
            }
        });
        select.addEventListener("click", () => {
            const selection = this.selectionFor(file);
            if (!selection) {
                return;
            }

            this.selection = selection;
            this.renderProperties();
        });
        row.append(select, remove);

        return row;
    }

    renderProperties() {
        if (!this.selection) {
            return;
        }

        const isEditing = this.editingLink instanceof HTMLAnchorElement;
        const modal = this.ensureModal();
        const content = document.createElement("div");
        const body = document.createElement("section");
        body.className = "lm-modal-body";
        const label = document.createElement("input");
        label.className = "form-control";
        label.value = this.editingLink?.textContent || this.selection.name;
        body.append(this.field(text("link_text"), label));
        const footer = document.createElement("footer");
        footer.className = "lm-modal-footer";
        if (isEditing) {
            const detach = document.createElement("button");
            detach.type = "button";
            detach.className = "btn btn-outline-danger me-auto";
            detach.textContent = text("remove_from_content");
            detach.addEventListener("click", () => {
                if (this.onDetach(this.editingLink) !== false) {
                    this.clearDetachedSelection();
                    modal.close({ reason: "detached" });
                }
            });
            footer.append(detach);
        }
        const back = document.createElement("button");
        back.type = "button";
        back.className = "btn btn-light";
        back.textContent = text(isEditing ? "change_file" : "back");
        back.addEventListener("click", () => {
            this.editingLink = null;
            void this.renderSelection();
        });
        const save = document.createElement("button");
        save.type = "button";
        save.className = "btn btn-primary";
        save.textContent = text(isEditing ? "save_file" : "insert_file");
        save.addEventListener("click", () => {
            if (this.onSave({ link: this.editingLink, selection: this.selection, label: label.value }) !== false) {
                modal.close({ reason: "saved" });
            }
        });
        footer.append(back, save);
        content.append(this.header(text(isEditing ? "edit_file" : "insert_file")), body, footer);
        modal.setContent(content);
        if (!modal.isOpen) {
            modal.open({ opener: this.opener, initialFocus: () => label });
        }
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
            const selection = this.selectionFor(uploadedFile);
            if (!selection) {
                throw new Error("File upload completion is invalid.");
            }

            this.selection = selection;
            this.renderProperties();
        } catch (error) {
            if (!this.uploadController.signal.aborted) {
                console.error("Rich-text file upload failed.", error);
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

    async selectionForFile(fileId) {
        const response = await HttpHelper.get(this.target.collectionUrl);
        if (response?.success !== true || !Array.isArray(response.files)) {
            return null;
        }

        return this.selectionFor(response.files.find((file) => Number(file?.id) === fileId));
    }

    selectionFor(file) {
        const fileId = Number(file?.id);
        if (!Number.isSafeInteger(fileId) || fileId <= 0) {
            return null;
        }

        return {
            fileId,
            name: file.display_name || file.original_filename || "",
            publicUrl: uploadUrl(this.target.publicDownloadUrlTemplate, fileId),
        };
    }

    clearDeletedSelection(fileId) {
        if (this.selection?.fileId !== fileId) {
            return;
        }

        this.editingLink = null;
        this.selection = null;
    }

    clearDetachedSelection() {
        this.editingLink = null;
        this.selection = null;
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
}
