/*!
 * Lemonade Rich Text Video Picker
 *
 * Provides the shared Admin dialog for inserting and editing external videos in Jodit.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { I18n } from "../core/lemonade-i18n.js";
import { Modal } from "./lemonade-modal.js";

function text(key) {
    return I18n.t(`admin.rich_text.${key}`);
}

let nextPickerId = 0;

export function externalVideoForUrl(value) {
    try {
        const url = new URL(value.trim());
        if (!["http:", "https:"].includes(url.protocol)) return null;
        url.protocol = "https:";
        const segments = url.pathname.split("/").filter(Boolean);

        if (["youtu.be", "www.youtu.be", "youtube.com", "www.youtube.com", "m.youtube.com", "music.youtube.com", "youtube-nocookie.com", "www.youtube-nocookie.com"].includes(url.hostname)) {
            const id = url.hostname.endsWith("youtu.be") ? segments[0]
                : segments[0] === "watch" ? url.searchParams.get("v")
                    : ["embed", "shorts", "live", "v"].includes(segments[0]) ? segments[1] : null;
            if (!/^[a-zA-Z0-9_-]{11}$/.test(id || "")) return null;

            return {
                provider: "YouTube",
                key: id,
                sourceUrl: segments[0] === "embed" ? `https://www.youtube.com/watch?v=${id}` : url.href,
                embedUrl: `https://www.youtube.com/embed/${id}`,
            };
        }

        if (["vimeo.com", "www.vimeo.com", "player.vimeo.com"].includes(url.hostname)) {
            const player = url.hostname === "player.vimeo.com";
            if (player && segments[0] !== "video") return null;
            const index = player ? 1 : segments.findIndex((part) => /^\d+$/.test(part));
            const id = segments[index];
            if (!/^\d+$/.test(id || "")) return null;
            const hash = url.searchParams.get("h") || (!player && /^[a-f0-9]{6,64}$/i.test(segments[index + 1] || "") ? segments[index + 1] : "");
            if (hash && !/^[a-f0-9]{6,64}$/i.test(hash)) return null;

            return {
                provider: "Vimeo",
                key: `${id}:${hash}`,
                sourceUrl: player ? `https://vimeo.com/${id}${hash ? `/${hash}` : ""}` : url.href,
                embedUrl: `https://player.vimeo.com/video/${id}${hash ? `?h=${hash}` : ""}`,
            };
        }

        if (["facebook.com", "www.facebook.com", "m.facebook.com", "web.facebook.com"].includes(url.hostname)) {
            const source = url.pathname === "/plugins/video.php" ? new URL(url.searchParams.get("href")) : url;
            if (!["facebook.com", "www.facebook.com", "m.facebook.com", "web.facebook.com"].includes(source.hostname)) return null;
            const parts = source.pathname.split("/").filter(Boolean);
            const videoIndex = parts.indexOf("videos");
            const id = videoIndex > 0 ? parts[videoIndex + 1]
                : ["watch", "video.php"].includes(parts[0]) ? source.searchParams.get("v") : null;
            if (!/^\d+$/.test(id || "")) return null;
            const permalink = videoIndex > 0
                ? `https://www.facebook.com/${parts[0]}/videos/${id}/`
                : `https://www.facebook.com/${parts[0] === "watch" ? "watch/" : "video.php"}?v=${id}`;

            return {
                provider: "Facebook",
                key: permalink,
                sourceUrl: source === url ? url.href : permalink,
                embedUrl: `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(permalink)}&show_text=false`,
            };
        }

        return null;
    } catch {
        return null;
    }
}

export class RichTextVideoPicker {
    constructor(opener, onSave, onDetach) {
        this.opener = opener;
        this.onSave = onSave;
        this.onDetach = onDetach;
        this.inputId = `lm-rich-text-video-url-${++nextPickerId}`;
    }

    openInsert() {
        this.editingVideo = null;
        this.render();
    }

    openEdit(iframe) {
        if (!(iframe instanceof HTMLIFrameElement) || !externalVideoForUrl(iframe.src)) return;

        this.editingVideo = iframe;
        this.render();
    }

    destroy() {
        this.modal?.destroy();
        this.modal = null;
    }

    ensureModal() {
        if (!this.modal) {
            this.modal = Modal.create({ size: "medium", kind: "rich-text-video" });
        }

        return this.modal;
    }

    render() {
        const iframe = this.editingVideo;
        const embeddedVideo = iframe ? externalVideoForUrl(iframe.src) : null;
        const originalVideo = iframe?.dataset.videoUrl ? externalVideoForUrl(iframe.dataset.videoUrl) : null;
        const modal = this.ensureModal();
        const form = document.createElement("form");
        form.noValidate = true;
        const header = document.createElement("header");
        header.className = "lm-modal-header";
        const title = document.createElement("h2");
        title.className = "lm-modal-title";
        title.setAttribute("data-lemonade-modal-title", "");
        title.textContent = text(iframe ? "edit_video" : "insert_video");
        const close = document.createElement("button");
        close.type = "button";
        close.className = "lm-modal-close";
        close.setAttribute("data-lemonade-modal-close", "");
        close.setAttribute("aria-label", text("close"));
        header.append(title, close);

        const body = document.createElement("section");
        body.className = "lm-modal-body";
        const field = document.createElement("div");
        field.className = "lm-form-group mb-3";
        const label = document.createElement("label");
        label.className = "lm-form-label";
        label.htmlFor = this.inputId;
        label.textContent = text("video_url");
        const input = document.createElement("input");
        input.id = this.inputId;
        input.type = "url";
        input.required = true;
        input.className = "form-control";
        input.placeholder = "https://";
        input.value = originalVideo && originalVideo.embedUrl === embeddedVideo?.embedUrl
            ? originalVideo.sourceUrl
            : embeddedVideo?.sourceUrl || "";
        const help = document.createElement("small");
        help.id = `${this.inputId}-help`;
        help.className = "form-text";
        help.textContent = text("video_supported_services");
        const error = document.createElement("small");
        error.id = `${this.inputId}-error`;
        error.className = "invalid-feedback";
        error.textContent = text("invalid_video_url");
        input.setAttribute("aria-describedby", `${help.id} ${error.id}`);
        input.addEventListener("input", () => {
            input.classList.remove("is-invalid");
            input.removeAttribute("aria-invalid");
        });
        field.append(label, input, help, error);
        body.append(field);

        const footer = document.createElement("footer");
        footer.className = "lm-modal-footer";
        if (iframe) {
            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "btn btn-outline-danger me-auto";
            remove.textContent = text("remove_video");
            remove.addEventListener("click", () => {
                if (this.onDetach(iframe) !== false) modal.close({ reason: "detached" });
            });
            footer.append(remove);
        }
        const cancel = document.createElement("button");
        cancel.type = "button";
        cancel.className = "btn btn-light";
        cancel.textContent = I18n.t("admin.common.cancel");
        cancel.addEventListener("click", () => modal.close({ reason: "cancel" }));
        const save = document.createElement("button");
        save.type = "submit";
        save.className = "btn btn-primary";
        save.textContent = text(iframe ? "save_video" : "insert_video");
        footer.append(cancel, save);
        form.append(header, body, footer);
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            const video = externalVideoForUrl(input.value);
            if (!video) {
                input.classList.add("is-invalid");
                input.setAttribute("aria-invalid", "true");
                input.focus();
                return;
            }

            if (this.onSave({ iframe, video }) !== false) modal.close({ reason: "saved" });
        });

        modal.setContent(form);
        modal.open({ opener: this.opener, initialFocus: () => input });
    }
}
