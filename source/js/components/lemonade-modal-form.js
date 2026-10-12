/*!
 * Lemonade Modal Form
 *
 * Loads and coordinates declarative modal form workflows.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpError } from "../core/lemonade-http-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Message } from "./lemonade-message.js";
import { Modal } from "./lemonade-modal.js";
import { destroyComponents, mountComponents } from "../lemonade-admin-components.js";
    export class ModalForm {
        static init() {
            if (this.initialized) {
                return;
            }

            this.initialized = true;
            EventHelper.delegate(document, "click", "[data-lemonade-modal-form-url]", (event, trigger) => {
                void this.handleTrigger(event, trigger);
            });
        }

        static async handleTrigger(event, trigger) {
            event.preventDefault();
            await this.open(trigger);
        }

        static async handleForm(event, form) {
            event.preventDefault();
            if (form.hasAttribute("data-lemonade-modal-form-submitting")) {
                return;
            }

            form.setAttribute("data-lemonade-modal-form-submitting", "");
            try {
                const response = await HttpHelper.request(form.action, {
                    method: form.method || "POST",
                    data: new window.FormData(form)
                });
                if (!response || typeof response.modalHtml !== "string") {
                    throw new HttpError("Modal content is missing.", 500, null);
                }

                await destroyComponents(this.content);
                this.modal.setContent(response.modalHtml);
                await this.initializeContent();
            } catch (error) {
                this.showError(error && error.response);
            } finally {
                form.removeAttribute("data-lemonade-modal-form-submitting");
            }
        }

        static async open(trigger) {
            const source = trigger.getAttribute("data-lemonade-modal-form-url");
            if (!source || trigger.hasAttribute("data-lemonade-modal-form-loading")) {
                return;
            }

            trigger.setAttribute("data-lemonade-modal-form-loading", "");
            try {
                const response = await HttpHelper.get(source);
                if (!response || typeof response.modalHtml !== "string") {
                    throw new HttpError("Modal content is missing.", 500, null);
                }

                if (Message) {
                    Message.clear();
                }
                this.ensureModal();
                this.configureSize(trigger);
                this.trigger = trigger;
                this.modal.setContent(response.modalHtml);
                await this.initializeContent();
                this.modal.open({
                    opener: trigger,
                    initialFocus: this.initialFocus.bind(this),
                });
            } catch (error) {
                this.showError(error && error.response);
            } finally {
                trigger.removeAttribute("data-lemonade-modal-form-loading");
            }
        }

        static ensureModal() {
            if (this.modal) {
                return;
            }

            this.modal = Modal.create({
                size: "medium",
                centered: true,
                scrollable: true,
                kind: "form",
            });
            this.element = this.modal.element;
            this.content = this.modal.content();
            EventHelper.on(this.element, "lemonade:action:success", (event) => {
                void this.handleSuccess(event);
            });
            EventHelper.on(this.element, "lemonade:modal:closed", () => {
                this.closing = this.handleClosed();
            });
        }

        static configureSize(trigger) {
            const size = this.normalizeSize(trigger.getAttribute("data-lemonade-modal-size"));

            this.modal.configure({ size });
        }

        static normalizeSize(size) {
            return ["small", "medium", "large", "full"].includes(size) ? size : "medium";
        }

        static async initializeContent() {
            this.bindContentForms();
            await I18n.apply(this.content);
            await mountComponents(this.content);
            DomHelper.queryAll("[data-lemonade-action]", this.content).forEach(function (action) {
                action.setAttribute("data-lemonade-defer-success-feedback", "");
            });
        }

        static bindContentForms() {
            this.formSubmitCleanup?.();
            this.formSubmitCleanup = EventHelper.delegate(this.content, "submit", "form[data-lemonade-modal-form]", (event, form) => {
                void this.handleForm(event, form);
            });
        }

        static async handleSuccess(event) {
            const form = event.detail && event.detail.form;
            if (!(form instanceof window.HTMLFormElement) || !this.element.contains(form)) {
                return;
            }

            if (form.lemonadeEditorLock) {
                await form.lemonadeEditorLock.release(false, false);
            }
            this.success = event.detail && event.detail.response ? event.detail.response : {};
            this.modal.close({ reason: "success" });
        }

        static initialFocus() {
            return DomHelper.queryAll("input, textarea, select, button", this.content).find(function (field) {
                return !field.disabled
                    && !field.readOnly
                    && field.type !== "hidden"
                    && field.getClientRects().length > 0;
            }) || this.content.querySelector("[data-lemonade-modal-close]");
        }

        static async handleClosed() {
            const success = this.success;
            const trigger = this.trigger;
            Message?.clear(this.modal.floatingRoot);
            this.formSubmitCleanup?.();
            this.formSubmitCleanup = null;
            await destroyComponents(this.content);
            this.content.replaceChildren();
            this.trigger = null;
            this.success = null;
            if (!success) {
                return;
            }
            if (trigger && (success.refreshGrid === true || trigger.getAttribute("data-lemonade-modal-refresh-grid") === "true")) {
                trigger.dispatchEvent(new CustomEvent("lemonade:grid:refresh", { bubbles: true }));
            }
            this.showSuccess(success);
        }

        static async closeForNavigation() {
            if (!this.modal?.element.open) {
                return;
            }

            this.modal.close({ reason: "navigation" });
            await this.closing;
        }

        static showError(response) {
            if (!Message) {
                return;
            }

            if (response && typeof response.messageKey === "string") {
                Message.show({
                    type: "error",
                    key: response.messageKey,
                    params: response.messageParams || {}
                });
                return;
            }

            Message.show({ type: "error", key: "admin.message.error" });
        }

        static showSuccess(response) {
            if (!Message) {
                return;
            }

            if (response && typeof response.messageKey === "string") {
                Message.show({
                    type: "success",
                    key: response.messageKey,
                    params: response.messageParams || {}
                });
                return;
            }

            Message.show({ type: "success", key: "admin.message.success" });
        }
    };
