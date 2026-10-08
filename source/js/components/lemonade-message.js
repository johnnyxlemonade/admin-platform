/*!
 * Lemonade Message
 *
 * Displays transient admin feedback messages.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { I18n } from "../core/lemonade-i18n.js";
    export class Message {
        static init() {
            if (this.initialized) {
                return;
            }

            this.initialized = true;
            this.ensureContainer();
            this.bindEvents();
            this.consumeFlashMessages();
        }

        static show(options) {
            this.init();

            const settings = Object.assign({ type: "info", timeout: 5000 }, options || {});
            const container = this.resolveContainer(settings.target);
            const timeout = Number(settings.timeout);
            const type = this.normalizeType(settings.type);
            const text = this.resolveText(settings);

            delete settings.target;
            settings.timeout = Number.isFinite(timeout) && timeout > 0 ? timeout : 0;

            if (!text) {
                return null;
            }

            this.enforceStackLimit();

            const item = document.createElement("div");
            const icon = document.createElement("i");
            const copy = document.createElement("span");
            const close = document.createElement("button");
            const record = { container: container, element: item, settings: settings, timeoutId: null, removeTimeoutId: null };

            item.className = `lm-message lm-message--${type}`;
            item.setAttribute("role", type === "warning" || type === "error" ? "alert" : "status");
            icon.className = `${BootstrapIcons.className(this.getIcon(type))} lm-message-icon`;
            icon.setAttribute("aria-hidden", "true");
            copy.className = "lm-message-copy";
            copy.textContent = text;
            close.className = "lm-message-close";
            close.type = "button";
            close.setAttribute("data-lemonade-message-close", "");
            close.setAttribute("aria-label", this.translate("admin.message.close", "Close"));
            close.title = this.translate("admin.message.close", "Close");
            close.innerHTML = '<i class="' + BootstrapIcons.className("x-lg") + '" aria-hidden="true"></i>';

            item.appendChild(icon);
            item.appendChild(copy);
            item.appendChild(close);

            if (settings.timeout > 0) {
                const progress = document.createElement("span");
                const progressBar = document.createElement("span");

                item.style.setProperty("--lm-message-timeout", `${settings.timeout}ms`);
                progress.className = "lm-message-progress";
                progressBar.className = "lm-message-progress-bar";
                progress.appendChild(progressBar);
                item.appendChild(progress);
            }

            container.prepend(item);
            this.records.push(record);

            if (settings.timeout > 0) {
                record.timeoutId = window.setTimeout(function () {
                    Message.dismiss(record);
                }, settings.timeout);
            }

            return item;
        }

        static success(message, timeout) { return this.show({ type: "success", message: message, timeout: timeout === undefined ? 5000 : timeout }); }
        static info(message, timeout) { return this.show({ type: "info", message: message, timeout: timeout === undefined ? 5000 : timeout }); }
        static warning(message, timeout) { return this.show({ type: "warning", message: message, timeout: timeout === undefined ? 5000 : timeout }); }
        static error(message, timeout) { return this.show({ type: "error", message: message, timeout: timeout === undefined ? 5000 : timeout }); }

        static clear(target) {
            const container = target ? this.findContainer(target) : null;
            if (target && !container) {
                return;
            }

            this.records.slice().filter(function (record) {
                return !container || record.container === container;
            }).forEach(function (record) {
                Message.remove(record);
            });
        }

        static ensureContainer() {
            this.container = document.querySelector("[data-lemonade-message-container]");

            if (this.container) {
                this.container.classList.add("lm-message-container");
                return;
            }

            this.container = document.createElement("div");
            this.container.className = "lm-message-container";
            this.container.setAttribute("data-lemonade-message-container", "");
            document.body.appendChild(this.container);
        }

        static resolveContainer(target) {
            if (!(target instanceof window.HTMLElement)) {
                return this.container;
            }

            let container = this.findContainer(target);
            if (container) {
                container.classList.add("lm-message-container");
                this.bindContainerEvents(container);
                return container;
            }

            container = document.createElement("div");
            container.className = "lm-message-container";
            container.setAttribute("data-lemonade-message-container", "");
            target.appendChild(container);
            this.bindContainerEvents(container);

            return container;
        }

        static findContainer(target) {
            return Array.from(target.children).find(function (child) {
                return child.matches("[data-lemonade-message-container]");
            }) || null;
        }

        static bindEvents() {
            this.bindContainerEvents(this.container);

            document.addEventListener("click", function (event) {
                const clear = event.target.closest("[data-lemonade-message-clear]");
                if (clear) {
                    event.preventDefault();
                    Message.clear();
                    return;
                }

                const trigger = event.target.closest("[data-lemonade-message-trigger]");
                if (!trigger) {
                    return;
                }

                event.preventDefault();
                Message.show(Message.getOptionsFromElement(trigger));
            });

            document.addEventListener("lemonade:locale:changed", function () {
                Message.records.forEach(function (record) {
                    Message.renderRecord(record);
                });
            });
        }

        static bindContainerEvents(container) {
            if (this.boundContainers.has(container)) {
                return;
            }

            this.boundContainers.add(container);
            container.addEventListener("click", function (event) {
                const close = event.target.closest("[data-lemonade-message-close]");
                if (!close) {
                    return;
                }

                const item = close.closest(".lm-message");
                const record = Message.records.find(function (candidate) {
                    return candidate.element === item;
                });
                if (record) {
                    Message.dismiss(record);
                    return;
                }

                item?.remove();
            });
        }

        static consumeFlashMessages() {
            Array.prototype.slice.call(document.querySelectorAll("[data-lemonade-message]")).forEach(function (element) {
                Message.show(Message.getOptionsFromElement(element));
                element.remove();
            });
        }

        static getOptionsFromElement(element) {
            const params = this.parseParams(element.getAttribute("data-lemonade-message-params"));

            return {
                type: element.getAttribute("data-lemonade-message-type") || "info",
                message: element.getAttribute("data-lemonade-message-text") || "",
                key: element.getAttribute("data-lemonade-message-key") || "",
                params: params,
                timeout: element.hasAttribute("data-lemonade-message-timeout")
                    ? Number(element.getAttribute("data-lemonade-message-timeout"))
                    : 5000
            };
        }

        static resolveText(settings) {
            if (settings.key) {
                return this.translate(settings.key, settings.message || "", settings.params);
            }

            return typeof settings.message === "string" ? settings.message : "";
        }

        static renderRecord(record) {
            const copy = record.element.querySelector(".lm-message-copy");
            const close = record.element.querySelector("[data-lemonade-message-close]");
            const text = this.resolveText(record.settings);
            const closeLabel = this.translate("admin.message.close", "Close");

            if (copy && text) {
                copy.textContent = text;
            }
            if (close) {
                close.setAttribute("aria-label", closeLabel);
                close.title = closeLabel;
            }
        }

        static dismiss(record) {
            if (!record || !record.element || record.element.classList.contains("is-leaving")) {
                return;
            }

            if (record.timeoutId) {
                window.clearTimeout(record.timeoutId);
                record.timeoutId = null;
            }
            const progress = record.element.querySelector(".lm-message-progress");
            if (progress) {
                progress.remove();
            }
            record.element.classList.add("is-leaving");
            record.removeTimeoutId = window.setTimeout(function () {
                Message.remove(record);
            }, 160);
        }

        static remove(record) {
            if (!record) {
                return;
            }
            if (record.timeoutId) {
                window.clearTimeout(record.timeoutId);
                record.timeoutId = null;
            }
            if (record.removeTimeoutId) {
                window.clearTimeout(record.removeTimeoutId);
                record.removeTimeoutId = null;
            }
            if (record.element && record.element.parentNode) {
                record.element.remove();
            }
            this.records = this.records.filter(function (candidate) {
                return candidate !== record;
            });
        }

        static enforceStackLimit() {
            while (this.records.length >= 3) {
                this.remove(this.records[0]);
            }
        }

        static normalizeType(type) {
            return ["success", "info", "warning", "error"].indexOf(type) !== -1 ? type : "info";
        }

        static getIcon(type) {
            return { success: "check-circle", info: "info-circle", warning: "exclamation-triangle", error: "x-circle" }[type];
        }

        static translate(key, fallback, params) {
            if (!I18n || typeof I18n.t !== "function") {
                return fallback;
            }

            const translated = I18n.t(key, params || {});
            return translated === key ? fallback : translated;
        }

        static parseParams(value) {
            if (!value) {
                return {};
            }
            try {
                return JSON.parse(value);
            } catch (error) {
                return {};
            }
        }
    };

    Message.records = [];
    Message.boundContainers = new WeakSet();

    function initializeStandaloneMessages() {
        if (!document.querySelector("[data-lemonade-admin-shell]")) {
            Message.init();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeStandaloneMessages);
    } else {
        initializeStandaloneMessages();
    }
