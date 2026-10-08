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
            await mountComponents(this.content);
            DomHelper.queryAll("[data-lemonade-action]", this.content).forEach(function (action) {
                action.setAttribute("data-lemonade-defer-success-feedback", "");
            });
            this.initializeCountryFlagPreviews();
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

        static initializeCountryFlagPreviews() {
            DomHelper.queryAll("[data-lemonade-country-flag-input]", this.content).forEach(function (input) {
                const preview = document.createElement("span");
                preview.className = "admin-country-flag ms-2";
                preview.setAttribute("aria-hidden", "true");
                input.insertAdjacentElement("afterend", preview);
                const render = function () {
                    const value = String(input.value || "").trim().toUpperCase();
                    preview.textContent = /^[A-Z]{2}$/.test(value)
                        ? String.fromCodePoint(0x1F1E6 + value.charCodeAt(0) - 65, 0x1F1E6 + value.charCodeAt(1) - 65)
                        : "";
                };
                EventHelper.on(input, "input", render);
                render();
            });
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
