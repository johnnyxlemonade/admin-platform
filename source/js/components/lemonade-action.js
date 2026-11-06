/*!
 * Lemonade Action
 *
 * Delegates declarative workflow actions and feedback.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { Confirm } from "./lemonade-confirm.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Message } from "./lemonade-message.js";
    export class Action {
        constructor(element) {
            this.element = element;
        }

        async execute(event) {
            event.preventDefault();

            const url = this.element.getAttribute("data-lemonade-url");
            const form = this.form();
            if (!url || this.element.disabled || this.element.hasAttribute("data-lemonade-action-in-flight") || this.isSubmitting(form) || (form && form.hasAttribute("data-lemonade-action-in-flight"))) {
                return;
            }

            const confirmation = Confirm.getOptionsFromElement(this.element);
            if (confirmation && !await Confirm.show(confirmation, this.element)) {
                return;
            }

            if (form) {
                this.clearValidationErrors(form);
                form.setAttribute("data-lemonade-action-in-flight", "");
                form.setAttribute("data-lemonade-submitting", "1");
            }
            this.element.setAttribute("data-lemonade-action-in-flight", "");
            this.setLoading(true);

            try {
                const response = await HttpHelper.request(url, {
                    method: this.element.getAttribute("data-lemonade-method") || "POST",
                    data: this.getPayload()
                });
                this.updateEditorVersion(response);
                this.updateEditorFields(response, form);
                if (!this.element.hasAttribute("data-lemonade-defer-success-feedback")) {
                    this.showFeedback(response && response.feedbackType === "info" ? "info" : "success", response);
                }
                const editUrl = this.fullPageCreateEditUrl(response, form);
                if (editUrl) {
                    if (typeof form.dispatchEvent === "function") {
                        form.dispatchEvent(new CustomEvent("lemonade:action:navigate", {
                            bubbles: true,
                            detail: { response: response, form: form, url: editUrl }
                        }));
                    }
                    window.location.assign(editUrl);
                    return;
                }
                this.element.dispatchEvent(new CustomEvent("lemonade:action:success", {
                    bubbles: true,
                    detail: { response: response, form: form }
                }));
                if (response && response.data && typeof response.data.successUrl === "string") {
                    window.location.assign(response.data.successUrl);
                    return;
                }
                if (response && response.refreshPage === true && !DomHelper.closest(this.element, "[data-lemonade-grid]")) {
                    window.location.assign(window.location.href);
                }
            } catch (error) {
                const handledValidation = this.renderValidationErrors(error && error.response);
                if (!handledValidation) {
                    this.showFeedback("error", error && error.response);
                }
                this.element.dispatchEvent(new CustomEvent("lemonade:action:error", { bubbles: true, detail: { error: error } }));
            } finally {
                this.setLoading(false);
                this.element.removeAttribute("data-lemonade-action-in-flight");
                if (form) {
                    form.removeAttribute("data-lemonade-action-in-flight");
                    form.removeAttribute("data-lemonade-submitting");
                }
            }
        }

        isSubmitting(form) {
            return form !== null && form.hasAttribute("data-lemonade-submitting");
        }

        getPayload() {
            const formId = this.element.getAttribute("data-lemonade-form");
            if (formId) {
                const form = this.form();
                const action = this.element.getAttribute("data-lemonade-action-key");
                if (!form || !action) {
                    return undefined;
                }

                const payload = {};
                const fallbacks = {};
                Array.prototype.slice.call(form.elements).forEach(function (control) {
                    if (!control.name || control.disabled || control.name === "_token" || control.name === "LEMONADE_CSRF") {
                        return;
                    }
                    if (control.hasAttribute("data-lemonade-action-fallback")) {
                        fallbacks[control.name] = control.value;
                        return;
                    }
                    if ((control.type === "checkbox" || control.type === "radio") && !control.checked) {
                        return;
                    }
                    const values = control.tagName === "SELECT" && control.multiple
                        ? Array.prototype.slice.call(control.options).filter(function (option) { return option.selected; }).map(function (option) { return option.value; })
                        : [control.value];
                    values.forEach(function (value) {
                        const array = /^([^\[]+)\[\]$/.exec(control.name);
                        if (array) {
                            payload[array[1]] = payload[array[1]] || [];
                            payload[array[1]].push(value);
                            return;
                        }
                        const nested = /^([^\[]+)\[([^\]]+)\]$/.exec(control.name);
                        if (nested) {
                            payload[nested[1]] = payload[nested[1]] || {};
                            payload[nested[1]][nested[2]] = value;
                            return;
                        }
                        if (Object.prototype.hasOwnProperty.call(payload, control.name)) {
                            payload[control.name] = Array.isArray(payload[control.name]) ? payload[control.name].concat(value) : [payload[control.name], value];
                            return;
                        }
                        payload[control.name] = value;
                    });
                });
                Object.keys(fallbacks).forEach(function (name) {
                    if (!Object.prototype.hasOwnProperty.call(payload, name)) {
                        payload[name] = fallbacks[name];
                    }
                });

                DomHelper.queryAll("[data-lemonade-permission-toggle]", form).forEach(function (toggle) {
                    if (toggle.disabled) {
                        return;
                    }
                    const hidden = toggle.previousElementSibling;
                    if (!hidden || !hidden.name) {
                        return;
                    }
                    const match = /^permission_overrides\[([^\]]+)\]$/.exec(hidden.name);
                    if (!match) {
                        return;
                    }
                    payload.permissions = payload.permissions || {};
                    payload.permissions[match[1]] = toggle.checked ? "1" : "0";
                    delete payload.permission_overrides;
                });

                return { action: action, payload: payload };
            }

            const value = this.element.getAttribute("data-lemonade-payload");

            if (!value) {
                return undefined;
            }

            try {
                return JSON.parse(value);
            } catch (error) {
                return undefined;
            }
        }

        fullPageCreateEditUrl(response, form) {
            const data = response && response.data;
            if (!form
                || this.element.getAttribute("data-lemonade-action-key") !== "create"
                || !form.hasAttribute("data-lemonade-navigate-to-edit-after-create")
                || !data
                || typeof data.editUrl !== "string") {
                return null;
            }

            return data.editUrl;
        }

        updateEditorVersion(response) {
            const form = this.form();
            const version = Number(response && response.data && response.data.version);
            if (!form || !Number.isSafeInteger(version) || version < 1) {
                return;
            }
            const field = form.querySelector("[data-lemonade-editor-version]")
                || form.querySelector('input[name="version"]');
            if (field) {
                field.value = String(version);
                field.setAttribute("data-lemonade-editor-version-value", String(version));
            }
        }

        updateEditorFields(response, form) {
            const fields = response && response.data && response.data.editorFields;
            if (!form || !form.hasAttribute("data-lemonade-editor-form") || !fields || typeof fields !== "object" || Array.isArray(fields)) {
                return;
            }

            Object.keys(fields).forEach(function (name) {
                const field = form.elements.namedItem(name);
                const value = fields[name];
                if (field && "value" in field && (typeof value === "string" || typeof value === "number")) {
                    field.value = String(value);
                }
            });
        }

        renderValidationErrors(response) {
            const form = this.form();
            if (!response || response.success !== false || !response.errors || typeof response.errors !== "object") {
                return false;
            }

            if (!form) {
                const formError = response.errors._form;
                if (typeof formError === "string" && formError) {
                    this.showFormError(formError);
                    return true;
                }

                if (typeof response.message === "string" && response.message) {
                    this.showFormError(response.message);
                    return true;
                }

                if (typeof response.messageKey === "string" && response.messageKey) {
                    this.showFormError(response.messageKey);
                    return true;
                }

                this.showFeedback("error", response);
                return true;
            }

            this.clearValidationErrors(form);
            let firstField = null;
            Object.keys(response.errors).forEach(function (fieldName) {
                const error = response.errors[fieldName];
                if (fieldName === "_form") {
                    this.showFormError(error);
                    return;
                }

                const field = form.querySelector(`[data-lemonade-field="${fieldName}"]`);
                if (!field) {
                    this.showFormError(error);
                    return;
                }
                Action.renderFieldValidationError(field, error);
                if (field.hasAttribute("data-lemonade-required-message")) {
                    field.setAttribute("data-lemonade-required-touched", "");
                }
                if (!firstField) {
                    firstField = field;
                }
            }, this);

            if (firstField) {
                this.activateTabForField(firstField);
                (firstField.lemonadeDateTime?.picker?.altInput || firstField).focus();
            }

            return true;
        }

        activateTabForField(field) {
            const panel = DomHelper.closest(field, ".lm-tab-panel[role=\"tabpanel\"], [role=\"tabpanel\"]");
            if (!panel || !panel.hidden) {
                return;
            }

            const tabsRoot = DomHelper.closest(panel, "[data-lemonade-tabs]");
            tabsRoot?.lemonadeTabs?.activate(panel.id);
        }

        clearValidationErrors(form) {
            DomHelper.queryAll("[data-lemonade-field]", form).forEach(function (field) {
                Action.clearFieldValidationErrors(field);
            });
            DomHelper.queryAll("[data-lemonade-field-error]", form).forEach(function (element) {
                element.remove();
            });
        }

        static renderFieldValidationError(field, error, messageKey = null) {
            field.classList.add("is-invalid");
            field.setAttribute("aria-invalid", "true");
            field.lemonadeSelect?.root.classList.add("is-invalid");
            field.lemonadeSelect?.trigger.setAttribute("aria-invalid", "true");
            const message = document.createElement("span");
            message.className = "lm-form-help is-error";
            message.setAttribute("data-lemonade-field-error", field.getAttribute("data-lemonade-field"));
            message.textContent = String(error);
            if (messageKey) {
                message.setAttribute("data-lemonade-live-required-error", "");
                message.setAttribute("data-lemonade-i18n", messageKey);
            }
            (field.closest(".lm-form-group") || field.parentElement).appendChild(message);
        }

        static validateRequiredField(field) {
            const messageKey = field.getAttribute("data-lemonade-required-message");
            if (!messageKey || field.disabled || field.readOnly) {
                return;
            }
            const form = field.form;
            const condition = field.getAttribute("data-lemonade-required-when");
            const state = condition ? form?.elements.namedItem(condition) : null;
            const required = state
                ? state.value === field.getAttribute("data-lemonade-required-value")
                : field.getAttribute("data-lemonade-required-active") === "1";
            const group = field.closest(".lm-form-group");
            const existing = group?.querySelector("[data-lemonade-field-error]");
            // Do not replace server-side range/type errors with the optional live check.
            if (existing && !existing.hasAttribute("data-lemonade-live-required-error") && existing.textContent !== I18n.t(messageKey)) {
                return;
            }
            Action.clearFieldValidationErrors(field);
            let value = field.lemonadeJoditRichText?.editor?.value ?? field.value ?? "";
            if (field.hasAttribute("data-lemonade-admin-rich-text")) {
                // Parse in an inert template only; never insert user HTML into the page.
                const fragment = document.createElement("template");
                fragment.innerHTML = value;
                value = fragment.content.textContent;
            }
            if (required && String(value).trim() === "") {
                Action.renderFieldValidationError(field, I18n.t(messageKey), messageKey);
            }
        }

        static clearFieldValidationErrors(field) {
            const form = field.form || field.closest("form");
            if (!form) {
                return;
            }

            const name = field.getAttribute("data-lemonade-field");
            const removedIds = [];
            DomHelper.queryAll("[data-lemonade-field-error]", form).forEach(function (message) {
                if (message.getAttribute("data-lemonade-field-error") === name) {
                    if (message.id) removedIds.push(message.id);
                    message.remove();
                }
            });
            const controls = [field, field.lemonadeDateTime?.picker?.altInput, field.lemonadeSelect?.trigger];
            controls.filter(Boolean).forEach(function (control) {
                control.classList.remove("is-invalid");
                control.removeAttribute("aria-invalid");
                const describedBy = control.getAttribute("aria-describedby");
                if (describedBy && removedIds.length) {
                    const remaining = describedBy.split(/\s+/).filter((id) => !removedIds.includes(id));
                    if (remaining.length) {
                        control.setAttribute("aria-describedby", remaining.join(" "));
                    } else {
                        control.removeAttribute("aria-describedby");
                    }
                }
            });
            field.lemonadeSelect?.root.classList.remove("is-invalid");
        }

        showFormError(error) {
            if (!Message || typeof error !== "string") {
                return;
            }

            const translated = I18n && typeof I18n.t === "function" ? I18n.t(error) : error;
            Message.show(this.messageOptions(translated !== error ? { type: "error", key: error } : { type: "error", message: error }));
        }

        messageOptions(options) {
            const target = this.form()?.closest("[data-lemonade-modal-root]")?.querySelector("[data-lemonade-modal-floating-root]");

            return target ? Object.assign({}, options, { target: target }) : options;
        }

        form() {
            const formId = this.element.getAttribute("data-lemonade-form");

            return formId ? document.getElementById(formId) : null;
        }

        showFeedback(type, response) {
            if (!Message) {
                return;
            }

            const prefix = `data-lemonade-message-${type}-`;
            const key = this.element.getAttribute(`${prefix}key`);
            const text = this.element.getAttribute(`${prefix}text`);
            const params = this.getMessageParams(`${prefix}params`);
            const timeout = this.element.hasAttribute(`${prefix}timeout`)
                ? Number(this.element.getAttribute(`${prefix}timeout`))
                : 5000;

            if (key) {
                Message.show(this.messageOptions({ type: type, key: key, params: params, timeout: timeout }));
                return;
            }
            if (text) {
                Message.show(this.messageOptions({ type: type, message: text, timeout: timeout }));
                return;
            }
            if (response && typeof response.messageKey === "string" && response.messageKey) {
                const responseParams = response.messageParams && typeof response.messageParams === "object"
                    ? response.messageParams
                    : {};
                Message.show(this.messageOptions({
                    type: type,
                    key: response.messageKey,
                    message: typeof response.message === "string" ? response.message : "",
                    params: responseParams,
                    timeout: timeout,
                }));
                return;
            }
            if (response && typeof response.message === "string" && response.message) {
                Message.show(this.messageOptions({ type: type, message: response.message, timeout: timeout }));
                return;
            }

            Message.show(this.messageOptions({ type: type, key: type === "success" ? "message.success" : "message.error", timeout: timeout }));
        }

        getMessageParams(attribute) {
            const value = this.element.getAttribute(attribute);
            if (!value) {
                return {};
            }
            try {
                return JSON.parse(value);
            } catch (error) {
                return {};
            }
        }

        setLoading(isLoading) {
            this.element.disabled = isLoading;
            this.element.classList.toggle("is-loading", isLoading);
            this.element.setAttribute("aria-busy", String(isLoading));
            this.element.setAttribute("aria-disabled", String(isLoading));
            const form = this.form();
            if (form) {
                form.classList.toggle("is-saving", isLoading);
                form.setAttribute("aria-busy", String(isLoading));
                DomHelper.queryAll('[type="submit"]', form).forEach(function (control) {
                    control.disabled = isLoading;
                    control.setAttribute("aria-disabled", String(isLoading));
                });
            }
        }

        static initAll(context) {
            if (this.isBound) {
                return;
            }

            this.isBound = true;
            ["input", "change"].forEach(function (eventName) {
                EventHelper.delegate(document, eventName, "form[data-lemonade-action-form] [data-lemonade-field]", function (event, field) {
                    Action.clearFieldValidationErrors(field);
                    if (eventName === "change" && field.tagName === "SELECT") {
                        field.setAttribute("data-lemonade-required-touched", "");
                        Action.validateRequiredField(field);
                    }
                    if (eventName === "change") {
                        DomHelper.queryAll("[data-lemonade-required-when][data-lemonade-required-touched]", field.form).forEach(function (dependent) {
                            if (dependent.getAttribute("data-lemonade-required-when") === field.name) {
                                Action.validateRequiredField(dependent);
                            }
                        });
                    }
                });
            });
            EventHelper.delegate(document, "focusout", "form[data-lemonade-action-form] .lm-form-group", function (event, group) {
                if (event.relatedTarget && group.contains(event.relatedTarget)) {
                    return;
                }
                const field = group.querySelector("[data-lemonade-required-message]");
                if (field) {
                    field.setAttribute("data-lemonade-required-touched", "");
                    Action.validateRequiredField(field);
                }
            });
            EventHelper.delegate(document, "click", "[data-lemonade-action]", function (event, element) {
                void new Action(element).execute(event);
            });
            EventHelper.delegate(document, "submit", "form[data-lemonade-action-form]", function (event, form) {
                if (!form.id) {
                    return;
                }
                if (form.hasAttribute("data-lemonade-submitting")) {
                    event.preventDefault();

                    return;
                }
                const element = document.querySelector(`[data-lemonade-action][data-lemonade-form="${form.id}"]`);
                if (element) {
                    void new Action(element).execute(event);
                }
            });
        }

        static mount(root = document) {
            this.initAll(root);
        }
    };

export function mount(root = document) {
    Action.mount(root);
}
