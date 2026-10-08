/*!
 * Lemonade File Upload
 *
 * Uploads shared Admin file usages through the canonical chunk transport.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { HttpError, HttpHelper } from "../core/lemonade-http-helper.js";
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Confirm } from "./lemonade-confirm.js";
import { Message } from "./lemonade-message.js";

let mountedCollectionCount = 0;

function refreshThumbnailState(event) {
    const state = event.detail;
    if (!state) {
        return;
    }
    document.querySelectorAll("[data-lemonade-file-module]").forEach((thumbnail) => {
        if (thumbnail.dataset.lemonadeFileModule !== state.module || thumbnail.dataset.lemonadeFileEntity !== state.entity || thumbnail.dataset.lemonadeFileUsage !== state.usage) {
            return;
        }
        const fallback = thumbnail.dataset.lemonadeFileFallback || "";
        thumbnail.replaceChildren();
        if (state.previewUrl) {
            const image = document.createElement("img");
            image.src = state.previewUrl;
            image.alt = thumbnail.dataset.lemonadeFileAlt || "";
            thumbnail.append(image);
        } else if (fallback) {
            const initials = document.createElement("span");
            initials.setAttribute("aria-hidden", "true");
            initials.textContent = fallback;
            thumbnail.append(initials);
        }
    });
}

class AdminFileUploadCollection {
    constructor(element) {
        this.element = element;
        this.input = element.querySelector("[data-lemonade-file-upload-input]");
        this.error = element.querySelector("[data-lemonade-file-upload-error]");
        this.items = element.querySelector("[data-lemonade-file-upload-items]");
        this.dropzone = element.querySelector(".lm-file-upload-dropzone");
        this.single = element.dataset.lemonadeFileUploadSingle === "true";
        this.avatar = element.dataset.lemonadeFileUploadPresentation === "avatar";
        this.avatarPresentation = element.hasAttribute("data-lemonade-file-upload-avatar");
        this.avatarProgress = element.querySelector("[data-lemonade-file-upload-avatar-progress]");
        this.avatarProgressRing = this.avatarProgress?.querySelector("[data-lemonade-file-upload-avatar-progress-ring]");
        this.singlePreview = element.querySelector("[data-lemonade-file-upload-single-preview]");
        this.avatarRemove = element.querySelector("[data-lemonade-file-upload-avatar-remove]");
        this.activeUploads = new Map();
        this.failedUploads = new Map();
        this.maxBytes = Number(element.dataset.lemonadeFileUploadMaxBytes);
        this.allowedExtensions = new Set(String(element.dataset.lemonadeFileUploadAllowedExtensions || "").split(",").map((extension) => extension.toLowerCase()).filter(Boolean));
        this.onInputChange = this.upload.bind(this);
        this.onDrop = this.drop.bind(this);
        this.onDragOver = (event) => event.preventDefault();
        this.onDragEnter = () => this.dropzone?.classList.add("is-dragover");
        this.onDragLeave = (event) => {
            if (!this.dropzone?.contains(event.relatedTarget)) {
                this.dropzone?.classList.remove("is-dragover");
            }
        };
        this.onClick = this.click.bind(this);
        this.onSort = this.sort.bind(this);
        this.onRename = this.rename.bind(this);
    }

    mount() {
        if (mountedCollectionCount++ === 0) {
            document.addEventListener("lemonade:file-upload:changed", refreshThumbnailState);
        }
        this.input?.addEventListener("change", this.onInputChange);
        if (!this.avatarPresentation) {
            this.element.addEventListener("dragover", this.onDragOver);
            this.element.addEventListener("dragenter", this.onDragEnter);
            this.element.addEventListener("dragleave", this.onDragLeave);
            this.element.addEventListener("drop", this.onDrop);
        }
        this.element.addEventListener("click", this.onClick);
        this.element.addEventListener("lemonade:sortable:change", this.onSort);
        document.addEventListener("lemonade:action:success", this.onRename);
    }

    destroy() {
        this.input?.removeEventListener("change", this.onInputChange);
        if (!this.avatarPresentation) {
            this.element.removeEventListener("dragover", this.onDragOver);
            this.element.removeEventListener("dragenter", this.onDragEnter);
            this.element.removeEventListener("dragleave", this.onDragLeave);
            this.element.removeEventListener("drop", this.onDrop);
        }
        this.element.removeEventListener("click", this.onClick);
        this.element.removeEventListener("lemonade:sortable:change", this.onSort);
        document.removeEventListener("lemonade:action:success", this.onRename);
        if (mountedCollectionCount > 0 && --mountedCollectionCount === 0) {
            document.removeEventListener("lemonade:file-upload:changed", refreshThumbnailState);
        }
        this.activeUploads.forEach((upload) => upload.controller.abort());
        this.activeUploads.clear();
        this.failedUploads.clear();
        delete this.element.lemonadeFileUploadCollection;
    }

    async upload() {
        await this.uploadFiles(Array.from(this.input?.files || []));
        if (this.input) {
            this.input.value = "";
        }
    }

    async drop(event) {
        event.preventDefault();
        this.dropzone?.classList.remove("is-dragover");
        await this.uploadFiles(Array.from(event.dataTransfer?.files || []));
    }

    async uploadFiles(files) {
        if (this.element.dataset.lemonadeFileUploadMultiple !== "true") {
            files = files.slice(0, 1);
        }
        for (const file of files) {
            await this.uploadFile(file);
        }
    }

    async uploadFile(file) {
        const item = this.pendingItem(file);
        const preflightError = this.preflightError(file);
        if (preflightError !== null) {
            if (this.avatarPresentation) {
                item.remove();
                Message.show({ type: "error", message: preflightError });
                return;
            }
            this.failedItem(item, preflightError);
            return;
        }

        const controller = new AbortController();
        let uploadId = null;
        let failed = false;
        const activeUpload = { controller: controller, uploadId: null, abortRequested: false };
        this.activeUploads.set(item, activeUpload);
        this.updateAvatarProgress(0);
        this.clearError();
        try {
            const started = await HttpHelper.post(this.element.dataset.lemonadeFileUploadStartUrl, { filename: file.name, size: file.size }, { signal: controller.signal });
            if (started?.success !== true || typeof started.uploadId !== "string") {
                throw new Error("Upload session could not be started.");
            }
            uploadId = started.uploadId;
            activeUpload.uploadId = uploadId;
            if (controller.signal.aborted) {
                await this.abortUpload(activeUpload);
                return;
            }
            let offset = Number(started.offset || 0);
            const chunkBytes = Number(started.chunkBytes || 2097152);
            while (offset < file.size) {
                const previousOffset = offset;
                const chunk = file.slice(offset, offset + chunkBytes);
                const response = await this.appendWithRetry(uploadId, offset, chunk, controller.signal);
                offset = Number(response?.offset);
                if (!Number.isSafeInteger(offset) || offset <= previousOffset || offset > file.size) {
                    throw new Error("Upload offset is invalid.");
                }
                item.querySelector("progress").value = offset;
                this.updateAvatarProgress((offset / file.size) * 100);
            }
            const completed = await HttpHelper.post(this.url("lemonadeFileUploadCompleteUrl", uploadId), undefined, { signal: controller.signal });
            if (completed?.success !== true || !Array.isArray(completed.files)) {
                throw new Error("Upload completion failed.");
            }
            this.render(completed.files);
        } catch (error) {
            if (controller.signal.aborted) {
                if (uploadId) {
                    void this.abortUpload(activeUpload);
                }
            } else {
                failed = true;
                this.failedItem(item, this.errorMessage(error));
                if (this.isRecoverableUploadFailure(error)) {
                    this.failedUploads.set(item, file);
                    this.retryItem(item);
                }
            }
        } finally {
            this.activeUploads.delete(item);
            this.clearAvatarProgress();
            if (!failed) {
                item.remove();
            }
        }
    }

    async appendWithRetry(uploadId, offset, chunk, signal) {
        let failure = null;
        for (let attempt = 0; attempt < 3; ++attempt) {
            try {
                return await HttpHelper.post(this.url("lemonadeFileUploadAppendUrl", uploadId), undefined, { headers: { "Upload-Offset": String(offset) }, rawBody: chunk, signal: signal });
            } catch (error) {
                failure = error;
                if (signal.aborted || attempt === 2 || !this.isRecoverableUploadFailure(error)) {
                    throw error;
                }
            }
        }

        throw failure;
    }

    async click(event) {
        const avatarSelect = event.target.closest("[data-lemonade-file-upload-avatar-select]");
        if (avatarSelect && this.element.contains(avatarSelect)) {
            this.input?.click();
            return;
        }
        const avatarRemove = event.target.closest("[data-lemonade-file-upload-avatar-remove]");
        if (avatarRemove && this.element.contains(avatarRemove)) {
            await this.removeAvatar(avatarRemove, event.target);
            return;
        }
        const tab = event.target.closest("[data-lemonade-file-upload-tab]");
        if (tab && this.element.contains(tab)) {
            this.selectTab(tab.dataset.lemonadeFileUploadTab);
            return;
        }
        const item = event.target.closest("[data-lemonade-file-upload-item]");
        if (!item || !this.element.contains(item)) {
            return;
        }
        if (event.target.closest("[data-lemonade-file-upload-discard]")) {
            await this.discardQueueItem(item);
            return;
        }
        if (event.target.closest("[data-lemonade-file-upload-retry]")) {
            const file = this.failedUploads.get(item);
            if (file) {
                this.failedUploads.delete(item);
                item.remove();
                await this.uploadFile(file);
            }
            return;
        }
        if (!event.target.closest("[data-lemonade-file-upload-remove]")) {
            return;
        }

        if (!await Confirm.show({
            title: "admin.common.delete",
            message: "admin.file_upload.remove_confirm",
            confirmText: "admin.common.delete",
            cancelText: "admin.common.cancel",
            variant: "danger",
        }, event.target)) {
            return;
        }

        try {
            const response = await HttpHelper.post(this.url("lemonadeFileUploadRemoveUrl", item.dataset.lemonadeSortableItem));
            if (response?.success !== true || !Array.isArray(response.files)) {
                throw new Error("File removal failed.");
            }
            this.render(response.files);
        } catch (error) {
            this.failedItem(item, this.errorMessage(error));
        }
    }

    async sort(event) {
        if (event.target !== this.items || !this.element.dataset.lemonadeFileUploadReorderUrl) {
            return;
        }
        try {
            const response = await HttpHelper.post(this.element.dataset.lemonadeFileUploadReorderUrl, { fileIds: event.detail.orderedIds });
            if (response?.success !== true || !Array.isArray(response.files)) {
                throw new Error("File reorder failed.");
            }
            this.render(response.files);
        } catch (error) {
            this.showGlobalError(error);
        }
    }

    rename(event) {
        const file = event.detail?.response?.file;
        if (!file || !Number.isSafeInteger(Number(file.id))) {
            return;
        }

        const container = this.single ? this.singlePreview : this.items;
        const item = container?.querySelector(`[data-lemonade-sortable-item="${String(file.id)}"]`);
        const name = item?.querySelector(this.single ? ".lm-file-upload-single-details strong" : ".lm-file-upload-item-copy strong");
        if (!name) {
            return;
        }

        name.textContent = file.display_name || file.original_filename;
    }

    pendingItem(file) {
        const item = document.createElement("article");
        item.className = this.avatarPresentation ? "lm-file-upload-avatar-feedback-item is-uploading" : "lm-file-upload-item is-uploading";
        item.dataset.lemonadeFileUploadItem = "";
        const copy = document.createElement("span");
        copy.className = "lm-file-upload-item-copy";
        const name = document.createElement("strong");
        name.textContent = file.name;
        const progress = document.createElement("progress");
        progress.max = file.size;
        progress.value = 0;
        const discard = this.iconButton("x-lg", this.translate("admin.file_upload.remove_from_queue", null, this.element.dataset.lemonadeFileUploadDiscardLabel || "Remove from queue"));
        discard.classList.add("is-danger");
        discard.dataset.lemonadeFileUploadDiscard = "";
        copy.append(name);
        item.append(copy, progress, discard);
        this.items?.append(item);

        return item;
    }

    retryItem(item) {
        const retry = this.iconButton("arrow-clockwise", this.translate("admin.file_upload.retry", null, this.element.dataset.lemonadeFileUploadRetryLabel || "Retry"));
        retry.dataset.lemonadeFileUploadRetry = "";
        item.append(retry);
    }

    failedItem(item, message) {
        item.classList.remove("is-uploading");
        item.classList.add("is-error");
        item.querySelector("progress")?.remove();
        const copy = item.querySelector(".lm-file-upload-item-copy");
        if (!copy) {
            return;
        }
        const error = document.createElement("small");
        error.className = "lm-file-upload-item-error";
        error.setAttribute("role", "alert");
        error.textContent = message;
        copy.querySelector(".lm-file-upload-item-error")?.remove();
        copy.append(error);
    }

    async discardQueueItem(item) {
        const activeUpload = this.activeUploads.get(item);
        this.failedUploads.delete(item);
        if (activeUpload) {
            activeUpload.controller.abort();
            await this.abortUpload(activeUpload);
        }
        item.remove();
    }

    async abortUpload(activeUpload) {
        if (!activeUpload.uploadId || activeUpload.abortRequested) {
            return;
        }
        activeUpload.abortRequested = true;
        await HttpHelper.post(this.url("lemonadeFileUploadAbortUrl", activeUpload.uploadId)).catch(() => undefined);
    }

    preflightError(file) {
        if (Number.isSafeInteger(this.maxBytes) && this.maxBytes > 0 && file.size > this.maxBytes) {
            return this.translate("admin.file_upload.file_too_large", { size: this.element.dataset.lemonadeFileUploadMaxSizeLabel || "" }, this.element.dataset.lemonadeFileUploadFileTooLargeLabel || "");
        }

        const extension = file.name.split(".").pop()?.toLowerCase() || "";
        if (!this.allowedExtensions.has(extension)) {
            return this.translate("admin.file_upload.extension_not_allowed", null, this.element.dataset.lemonadeFileUploadExtensionNotAllowedLabel || "");
        }

        return null;
    }

    isRecoverableUploadFailure(error) {
        if (error instanceof HttpError) {
            return error.status === 408 || error.status === 429 || error.status >= 500;
        }

        return error instanceof TypeError;
    }

    render(files) {
        if (this.avatarPresentation) {
            this.renderAvatar(files[0] || null);
            return;
        }
        if (this.single) {
            this.singlePreview?.replaceChildren(...(files.length > 0 ? [this.fileItem(files[0])] : []));
            this.items?.replaceChildren();
            this.selectTab(this.avatar || files.length > 0 ? "preview" : "upload");
            this.publishSingleState(files[0] || null);
            return;
        }
        if (!this.items) {
            return;
        }
        this.items.replaceChildren(...files.map((file) => this.fileItem(file)));
    }

    renderAvatar(file) {
        this.items?.replaceChildren();
        this.clearAvatarProgress();
        this.avatarRemove.hidden = file === null;
        this.avatarRemove.dataset.lemonadeFileUploadAvatarFileId = file === null ? "" : String(file.id);
        this.publishSingleState(file);
    }

    updateAvatarProgress(progress) {
        if (!this.avatarPresentation || !this.avatarProgress || !this.avatarProgressRing) {
            return;
        }

        const normalizedProgress = Math.min(100, Math.max(0, progress));
        this.avatarProgress.classList.add("is-active");
        this.avatarProgress.classList.toggle("is-indeterminate", normalizedProgress === 0);
        this.avatarProgressRing.style.strokeDashoffset = String(100 - normalizedProgress);
    }

    clearAvatarProgress() {
        if (!this.avatarPresentation || !this.avatarProgress || !this.avatarProgressRing) {
            return;
        }

        this.avatarProgress.classList.remove("is-active");
        this.avatarProgress.classList.remove("is-indeterminate");
        this.avatarProgressRing.style.removeProperty("stroke-dashoffset");
    }

    async removeAvatar(button, opener) {
        const fileId = button.dataset.lemonadeFileUploadAvatarFileId;
        if (!fileId || !await Confirm.show({
            title: "admin.common.delete",
            message: "admin.file_upload.remove_confirm",
            confirmText: "admin.common.delete",
            cancelText: "admin.common.cancel",
            variant: "danger",
        }, opener)) {
            return;
        }

        try {
            const response = await HttpHelper.post(this.url("lemonadeFileUploadRemoveUrl", fileId));
            if (response?.success !== true || !Array.isArray(response.files)) {
                throw new Error("File removal failed.");
            }
            this.renderAvatar(response.files[0] || null);
        } catch (error) {
            this.showGlobalError(error);
        }
    }

    publishSingleState(file) {
        const target = String(this.element.dataset.lemonadeFileUploadStartUrl || "").match(/\/files\/([^/]+)\/([^/]+)\/(\d+)\/chunks/);
        if (!target) {
            return;
        }
        document.dispatchEvent(new CustomEvent("lemonade:file-upload:changed", {
            detail: {
                module: target[1],
                usage: target[2],
                entity: target[3],
                previewUrl: typeof file?.preview_url === "string" ? file.preview_url : null,
            },
        }));
    }

    fileItem(file) {
        if (this.single) {
            return this.singleItem(file);
        }

        const item = document.createElement("article");
        item.className = "lm-file-upload-item";
        item.dataset.lemonadeFileUploadItem = "";
        item.dataset.lemonadeSortableItem = String(file.id);
        if (typeof file.preview_url === "string") {
            const image = document.createElement("img");
            image.className = "lm-file-upload-item-preview";
            image.src = file.preview_url;
            image.alt = "";
            item.append(image);
        } else {
            const type = document.createElement("span");
            type.className = "lm-file-upload-item-type";
            type.setAttribute("aria-hidden", "true");
            type.textContent = String(file.extension || "").toUpperCase();
            item.append(type);
        }
        const copy = document.createElement("span");
        copy.className = "lm-file-upload-item-copy";
        const name = document.createElement("strong");
        name.textContent = file.display_name || file.original_filename;
        const metadata = document.createElement("small");
        metadata.textContent = String(file.metadata || "");
        copy.append(name, metadata);
        item.append(copy);
        if (this.items?.hasAttribute("data-lemonade-sortable")) {
            const handle = this.iconButton("arrows-move", this.translate("admin.file_upload.reorder", null, this.element.dataset.lemonadeFileUploadReorderLabel || "Reorder"));
            handle.classList.add("lm-file-upload-item-handle");
            handle.dataset.lemonadeSortableHandle = "";
            item.append(handle);
        }
        const renameUrl = this.url("lemonadeFileUploadRenameModalUrl", file.id);
        if (renameUrl) {
            const edit = this.iconButton("pencil-square", this.translate("admin.file_upload.edit", null, this.element.dataset.lemonadeFileUploadEditLabel));
            edit.dataset.lemonadeModalFormUrl = renameUrl;
            edit.dataset.lemonadeModalSize = "medium";
            item.append(edit);
        }
        const remove = this.iconButton("trash3", this.translate(this.element.dataset.lemonadeFileUploadRemoveKey || "admin.file_upload.remove", null, this.element.dataset.lemonadeFileUploadRemoveLabel || "Remove"));
        remove.classList.add("is-danger");
        remove.dataset.lemonadeFileUploadRemove = "";
        item.append(remove);

        return item;
    }

    singleItem(file) {
        const item = document.createElement("article");
        const presentation = this.element.dataset.lemonadeFileUploadPresentation || "standard";
        item.className = `lm-file-upload-single lm-file-upload-single--${presentation}`;
        item.dataset.lemonadeFileUploadItem = "";
        item.dataset.lemonadeSortableItem = String(file.id);

        if (presentation !== "avatar" && typeof file.preview_url === "string") {
            const image = document.createElement("img");
            image.className = "lm-file-upload-single-image";
            image.src = file.preview_url;
            image.alt = "";
            item.append(image);
        }

        const details = document.createElement("div");
        details.className = "lm-file-upload-single-details";
        const name = document.createElement("strong");
        name.textContent = file.display_name || file.original_filename;
        const metadata = document.createElement("small");
        metadata.textContent = String(file.metadata || "");
        details.append(name, metadata);
        item.append(details);

        const actions = document.createElement("span");
        actions.className = "lm-file-upload-item-actions";
        const renameUrl = this.url("lemonadeFileUploadRenameModalUrl", file.id);
        if (renameUrl) {
            const edit = this.iconButton("pencil-square", this.translate("admin.file_upload.edit", null, this.element.dataset.lemonadeFileUploadEditLabel));
            edit.dataset.lemonadeModalFormUrl = renameUrl;
            edit.dataset.lemonadeModalSize = "medium";
            actions.append(edit);
        }
        const remove = this.iconButton("trash3", this.translate(this.element.dataset.lemonadeFileUploadRemoveKey || "admin.file_upload.remove", null, this.element.dataset.lemonadeFileUploadRemoveLabel || "Remove"));
        remove.classList.add("is-danger");
        remove.dataset.lemonadeFileUploadRemove = "";
        actions.append(remove);
        item.append(actions);

        return item;
    }

    selectTab(name) {
        if (!this.single || !["preview", "upload"].includes(name)) {
            return;
        }
        this.element.querySelectorAll("[data-lemonade-file-upload-tab]").forEach((tab) => {
            const active = tab.dataset.lemonadeFileUploadTab === name;
            tab.classList.toggle("is-active", active);
            tab.setAttribute("aria-selected", active ? "true" : "false");
        });
        this.element.querySelectorAll("[data-lemonade-file-upload-panel]").forEach((panel) => {
            panel.hidden = panel.dataset.lemonadeFileUploadPanel !== name;
        });
    }

    iconButton(icon, label) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-light btn-sm lm-file-upload-item-action";
        button.setAttribute("aria-label", label);
        button.title = label;
        const iconElement = document.createElement("i");
        BootstrapIcons.set(iconElement, icon);
        iconElement.setAttribute("aria-hidden", "true");
        button.append(iconElement);

        return button;
    }

    url(key, uploadId) {
        return String(this.element.dataset[key] || "").replace("__upload_id__", encodeURIComponent(uploadId));
    }

    clearError() {
        if (this.error) {
            this.error.hidden = true;
            this.error.textContent = "";
        }
    }

    errorMessage(error) {
        const message = error?.response?.errors?.file;
        return typeof message === "string" ? message : this.translate("admin.file_upload.failed", null, this.element.dataset.lemonadeFileUploadFailedLabel || "The file could not be uploaded.");
    }

    translate(key, params, fallback) {
        const translation = I18n.t(key, params || undefined);

        return translation === key ? fallback : translation;
    }

    showGlobalError(error) {
        const message = this.errorMessage(error);
        if (this.error) {
            this.error.hidden = false;
            this.error.textContent = message;
            return;
        }

        Message.show({ type: "error", key: "admin.message.error" });
    }
}

export function mount(root = document) {
    const collections = root.matches?.("[data-lemonade-file-upload-collection]")
        ? [root]
        : Array.from(root.querySelectorAll("[data-lemonade-file-upload-collection]"));
    collections.forEach(function (element) {
        if (!element.lemonadeFileUploadCollection) {
            const component = new AdminFileUploadCollection(element);
            component.mount();
            element.lemonadeFileUploadCollection = component;
        }
    });
}

export function destroy(root = document) {
    const collections = root.matches?.("[data-lemonade-file-upload-collection]") ? [root] : Array.from(root.querySelectorAll("[data-lemonade-file-upload-collection]"));
    collections.forEach((element) => element.lemonadeFileUploadCollection?.destroy());
}
