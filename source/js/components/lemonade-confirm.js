/*!
 * Lemonade Confirm
 *
 * Provides confirmation dialogs for actions through the shared Admin Modal.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Message } from "./lemonade-message.js";
import { Modal } from "./lemonade-modal.js";
    export class Confirm {
        static init() {
            if (this.initialized) {
                return;
            }

            this.initialized = true;
            this.ensureModal();
            this.bindEvents();
        }

        static show(options, trigger) {
            this.init();

            if (!this.modal) {
                return Promise.resolve(false);
            }

            if (this.resolve) {
                this.resolve(false);
            }

            this.options = Object.assign({
                title: "admin.common.confirm",
                message: "",
                confirmText: "admin.common.continue",
                cancelText: "admin.common.cancel",
                variant: "default"
            }, options || {});
            this.trigger = trigger || null;
            if (Message) {
                Message.clear();
            }
            this.render();

            return new Promise(function (resolve) {
                Confirm.resolve = resolve;
                Confirm.modal.open({
                    opener: Confirm.trigger,
                    initialFocus: () => Confirm.element.querySelector("[data-lemonade-confirm-cancel]"),
                });
            });
        }

        static getOptionsFromElement(element) {
            if (!element || !element.hasAttribute("data-lemonade-confirm")) {
                return null;
            }

            return {
                title: element.getAttribute("data-lemonade-confirm-title") || "admin.common.confirm",
                message: element.getAttribute("data-lemonade-confirm-message") || element.getAttribute("data-lemonade-confirm"),
                confirmText: element.getAttribute("data-lemonade-confirm-text") || "admin.common.continue",
                cancelText: element.getAttribute("data-lemonade-confirm-cancel-text") || "admin.common.cancel",
                variant: element.getAttribute("data-lemonade-confirm-variant") || "default"
            };
        }

        static ensureModal() {
            this.modal = Modal.create({ size: "small", centered: true, kind: "confirm" });
            this.element = this.modal.content();
            this.modal.element.setAttribute("data-lemonade-confirm-modal", "");
            this.modal.setContent('<section class="lm-modal-body" data-lemonade-modal-body><div class="lm-confirm-icon" data-lemonade-confirm-icon aria-hidden="true"><i class="' + BootstrapIcons.className("question-circle") + '"></i></div><div class="lm-confirm-copy"><h2 id="lemonade-confirm-title" data-lemonade-modal-title data-lemonade-confirm-title></h2><p data-lemonade-confirm-message></p></div></section><footer class="lm-modal-footer"><button class="btn btn-light" type="button" data-lemonade-modal-close="cancel" data-lemonade-confirm-cancel></button><button class="btn btn-primary lm-button-primary" type="button" data-lemonade-confirm-accept></button></footer>');
            this.element = this.modal.content();
        }

        static bindEvents() {
            const self = this;
            EventHelper.on(this.element, "click", function (event) {
                if (event.target.closest("[data-lemonade-confirm-accept]")) {
                    self.close(true);
                    return;
                }
            });
            EventHelper.on(this.modal.element, "lemonade:modal:closed", function () {
                self.settle(false);
                self.trigger = null;
            });
            EventHelper.on(document, "lemonade:locale:changed", function () {
                if (self.options) {
                    self.render();
                }
            });
        }

        static close(value) {
            this.settle(value);
            this.modal.close({ reason: value ? "success" : "cancel" });
        }

        static render() {
            const variant = this.options.variant === "danger" ? "danger" : "default";
            const icon = this.element.querySelector("[data-lemonade-confirm-icon]");
            const title = this.element.querySelector("[data-lemonade-confirm-title]");
            const message = this.element.querySelector("[data-lemonade-confirm-message]");
            const accept = this.element.querySelector("[data-lemonade-confirm-accept]");
            const cancel = this.element.querySelector("[data-lemonade-confirm-cancel]");

            this.modal.element.classList.toggle("is-danger", variant === "danger");
            icon.innerHTML = '<i class="' + BootstrapIcons.className(variant === "danger" ? "exclamation-triangle" : "question-circle") + '"></i>';
            title.textContent = I18n.t(this.options.title);
            message.textContent = I18n.t(this.options.message);
            accept.textContent = I18n.t(this.options.confirmText);
            cancel.textContent = I18n.t(this.options.cancelText);
            accept.classList.toggle("btn-danger", variant === "danger");
            accept.classList.toggle("btn-primary", variant !== "danger");
            accept.classList.toggle("lm-button-primary", variant !== "danger");
        }

        static settle(value) {
            if (!this.resolve) {
                return;
            }

            const resolve = this.resolve;
            this.resolve = null;
            resolve(value);
        }
    };
