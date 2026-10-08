/*!
 * Lemonade Editor Lock
 *
 * Maintains editor lock ownership while an editable form is open.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { Message } from "./lemonade-message.js";
    export class EditorLock {
        constructor(form) {
            this.form = form;
            this.basePath = form.getAttribute("data-lemonade-editor-lock-base-path");
            this.module = form.getAttribute("data-lemonade-editor-lock-module");
            this.entity = form.getAttribute("data-lemonade-editor-lock-entity");
            this.released = false;
            this.releasing = null;
            this.destroyed = false;
            this.listenerRemovers = [];
        }
        init() {
            if (!this.basePath || !this.module || !this.entity) { return false; }
            this.listenerRemovers.push(EventHelper.on(document, "click", (event) => {
                void this.releaseBeforeNavigation(event);
            }));
            const modal = this.form.closest("[data-lemonade-modal-root]");
            if (modal) {
                this.listenerRemovers.push(EventHelper.on(modal, "lemonade:modal:closed", () => {
                    void this.release(false, false);
                }));
            }
            return true;
        }
        async releaseBeforeNavigation(event) {
            if (event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            const link = event.target?.closest?.("a[href]");
            if (!link || link.target || link.hasAttribute("download")) {
                return;
            }
            let destination;
            try { destination = new window.URL(link.href, window.location.href); }
            catch (error) { return; }
            if (destination.origin !== window.location.origin || destination.href === window.location.href) {
                return;
            }

            event.preventDefault();
            await this.release(false, false);
            window.location.assign(destination.href);
        }
        release(keepalive, showError) {
            if (this.released) { return Promise.resolve(true); }
            if (this.releasing) { return this.releasing; }
            this.releasing = this.send("release", keepalive === true, showError === true).then((released) => {
                if (released) {
                    this.released = true;
                }
                return released;
            }).finally(() => { this.releasing = null; });
            return this.releasing;
        }
        async send(operation, keepalive) {
            const url = this.basePath + "/api/editor/" + encodeURIComponent(this.module) + "/" + encodeURIComponent(this.entity) + "/" + operation;
            try {
                await HttpHelper.request(url, { method: "POST", data: {}, keepalive: keepalive === true });
                return true;
            } catch (error) {
                if (error && error.status === 409 && Message && keepalive !== true) { Message.show({ type: "error", key: "admin.editor.lock_lost" }); }
                return false;
            }
        }
        async destroy() {
            if (this.destroyed) {
                return;
            }

            this.destroyed = true;
            this.listenerRemovers.forEach(function (remove) { remove(); });
            this.listenerRemovers = [];
            await this.release(false, false);
            delete this.form.lemonadeEditorLock;
            this.form.removeAttribute("data-lemonade-editor-lock-mounted");
        }
        static applyMetadata(form, editUrl) {
            let url;
            try { url = new window.URL(editUrl || form.action, window.location.href); }
            catch (error) { return false; }
            const match = /^(.*)\/system\/([^/]+)\/edit\/([^/]+)$/.exec(url.pathname)
                ?? /^(.*)\/([^/]+)\/edit\/([^/]+)$/.exec(url.pathname);
            if (!match) { return false; }
            form.setAttribute("data-lemonade-editor-lock", "");
            form.setAttribute("data-lemonade-editor-lock-base-path", match[1]);
            form.setAttribute("data-lemonade-editor-lock-module", match[2]);
            form.setAttribute("data-lemonade-editor-lock-entity", match[3]);
            return true;
        }
        static initAll(context) {
            const root = context || document;
            const forms = root instanceof window.HTMLFormElement && root.hasAttribute("data-lemonade-action-form")
                ? [root]
                : DomHelper.queryAll("[data-lemonade-action-form]", root);
            forms.forEach(function (form) {
                if (!EditorLock.applyMetadata(form) || form.hasAttribute("data-lemonade-editor-lock-mounted")) {
                    return;
                }
                const lock = new EditorLock(form);
                if (lock.init()) {
                    form.lemonadeEditorLock = lock;
                    form.setAttribute("data-lemonade-editor-lock-mounted", "");
                }
            });
        }

        static mount(root = document) {
            this.initAll(root);
        }
        static releaseFor(form) {
            return form && form.lemonadeEditorLock
                ? form.lemonadeEditorLock.release(false, false)
                : Promise.resolve(true);
        }

        static async destroy(root = document) {
            const forms = root.matches && root.matches("[data-lemonade-action-form]") ? [root] : [];
            forms.push(...DomHelper.queryAll("[data-lemonade-action-form]", root));
            await Promise.all(forms.map(function (form) {
                if (form.lemonadeEditorLock) {
                    return form.lemonadeEditorLock.destroy();
                }
                return Promise.resolve();
            }));
        }
    };

export async function destroy(root = document) {
    await EditorLock.destroy(root);
}

export function mount(root = document) {
    EditorLock.mount(root);
}
