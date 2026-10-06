/*!
 * Lemonade Form Dirty State
 *
 * Tracks persisted values for full-page AdminEditor forms.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { Confirm } from "./lemonade-confirm.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
    export class FormDirtyState {
        constructor(form, saveBar) {
            this.form = form;
            this.saveBar = saveBar;
            this.isDirty = false;
            this.navigationSuspended = false;
            this.destroyed = false;
            this.baseline = this.capture();
            this.resettableMarkup = this.captureResettableMarkup();
            this.handleUserChange = this.handleUserChange.bind(this);
            this.form.addEventListener("input", this.handleUserChange);
            this.form.addEventListener("change", this.handleUserChange);
        }

        destroy() {
            this.destroyed = true;
            this.form.removeEventListener("input", this.handleUserChange);
            this.form.removeEventListener("change", this.handleUserChange);
            FormDirtyState.instances.delete(this.form);
            this.resettableMarkup = [];
        }

        capture() {
            const occurrences = {};
            const entries = Array.prototype.slice.call(this.form.elements || []).map(function (control) {
                if (!this.isRelevant(control)) {
                    return null;
                }

                const key = this.controlKey(control, occurrences);

                return { key: key, value: this.controlValue(control) };
            }, this).filter(Boolean);

            entries.sort(function (left, right) {
                return left.key.localeCompare(right.key);
            });

            return JSON.stringify(entries);
        }

        captureResettableMarkup() {
            return DomHelper.queryAll("[data-lemonade-dirty-state-resettable]", this.form).map(function (element) {
                return { element: element, markup: element.innerHTML };
            });
        }

        controlKey(control, occurrences) {
            const permissionCode = control.getAttribute("data-lemonade-permission-code");
            if (permissionCode && control.hasAttribute("data-lemonade-permission-toggle")) {
                return "permission:" + permissionCode;
            }

            const stateKey = control.getAttribute("data-lemonade-dirty-state-key");
            const base = stateKey || control.name + ":" + (control.type || control.tagName || "control").toLowerCase();
            occurrences[base] = (occurrences[base] || 0) + 1;

            return base + ":" + occurrences[base];
        }

        controlValue(control) {
            if (control.tagName === "SELECT" && control.multiple) {
                return Array.prototype.slice.call(control.options).filter(function (option) {
                    return option.selected;
                }).map(function (option) {
                    return option.value;
                });
            }
            if (control.type === "checkbox" || control.type === "radio") {
                return control.checked === true;
            }

            return String(control.value == null ? "" : control.value);
        }

        isRelevant(control) {
            if (!control || !control.tagName) {
                return false;
            }
            const type = (control.type || "").toLowerCase();
            if (control.tagName === "BUTTON" || ["button", "submit", "reset", "image", "file"].indexOf(type) !== -1) {
                return false;
            }
            if (control.hasAttribute("data-lemonade-permission-group-toggle")) {
                return false;
            }
            if (control.hasAttribute("data-lemonade-transport-only") || control.hasAttribute("data-lemonade-editor-version")) {
                return false;
            }
            if (["_token", "LEMONADE_CSRF", "version"].indexOf(control.name) !== -1) {
                return false;
            }

            return Boolean(control.name) || control.hasAttribute("data-lemonade-dirty-state-key") || control.hasAttribute("data-lemonade-permission-toggle");
        }

        handleUserChange(event) {
            this.updateDirty();
            this.form.dispatchEvent(new CustomEvent("lemonade:dirty-state:user-change", {
                bubbles: true,
                detail: { originalEvent: event }
            }));
        }

        updateDirty() {
            this.setDirty(this.capture() !== this.baseline);
        }

        setDirty(isDirty) {
            this.isDirty = isDirty;
            if (!isDirty) {
                this.navigationSuspended = false;
            }
            this.form.toggleAttribute("data-lemonade-dirty", isDirty);
            this.saveBar.hidden = !isDirty;
        }

        async requestNavigation(trigger, navigate) {
            if (this.navigationSuspended) {
                return false;
            }

            this.navigationSuspended = true;
            const confirmed = await Confirm.show({
                title: "admin.editor.unsaved_changes_navigation",
                message: "admin.editor.unsaved_changes_navigation_message",
                confirmText: "admin.common.leave_without_saving",
                cancelText: "admin.common.stay",
                variant: "danger"
            }, trigger);
            if (!confirmed) {
                this.navigationSuspended = false;

                return false;
            }

            if (this.destroyed) {
                return false;
            }

            navigate();
            return true;
        }

        shouldGuardNavigation() {
            return this.isDirty && !this.navigationSuspended;
        }

        resetBaseline() {
            this.baseline = this.capture();
            this.resettableMarkup = this.captureResettableMarkup();
            this.setDirty(false);
            this.form.dispatchEvent(new CustomEvent("lemonade:dirty-state:save", { bubbles: true }));
        }

        discard() {
            this.restoreControls();
            this.resettableMarkup.forEach(function (snapshot) {
                snapshot.element.innerHTML = snapshot.markup;
            });
            this.clearValidationPresentation();
            this.form.dispatchEvent(new CustomEvent("lemonade:permission-overrides:rebind", { bubbles: true }));
            this.setDirty(false);
            this.form.dispatchEvent(new CustomEvent("lemonade:dirty-state:discard", { bubbles: true }));
        }

        restoreControls() {
            const baseline = JSON.parse(this.baseline);
            const values = new Map(baseline.map(function (entry) {
                return [entry.key, entry.value];
            }));
            const occurrences = {};

            Array.prototype.slice.call(this.form.elements || []).forEach(function (control) {
                if (!this.isRelevant(control)) {
                    return;
                }
                const key = this.controlKey(control, occurrences);
                if (!values.has(key)) {
                    return;
                }
                const value = values.get(key);
                if (control.tagName === "SELECT" && control.multiple) {
                    Array.prototype.slice.call(control.options).forEach(function (option) {
                        option.selected = value.indexOf(option.value) !== -1;
                    });
                    return;
                }
                if (control.type === "checkbox" || control.type === "radio") {
                    control.checked = value === true;
                    return;
                }
                control.value = value;
            }, this);
        }

        clearValidationPresentation() {
            DomHelper.queryAll("[data-lemonade-field-error]", this.form).forEach(function (element) {
                element.remove();
            });
            DomHelper.queryAll("[data-lemonade-field].is-invalid", this.form).forEach(function (field) {
                field.classList.remove("is-invalid");
                field.removeAttribute("aria-invalid");
            });
        }

        static initAll(context) {
            const root = context || document;
            const forms = [];
            if (root.matches && root.matches("form[data-lemonade-editor-form]")) {
                forms.push(root);
            }
            DomHelper.queryAll("form[data-lemonade-editor-form]", root).forEach(function (form) {
                forms.push(form);
            });
            forms.forEach(function (form) {
                if (form.closest("[data-lemonade-modal-root]") || this.instances.has(form)) {
                    return;
                }
                const editor = form.closest("[data-lemonade-admin-editor]");
                const saveBar = editor && editor.querySelector("[data-lemonade-save-bar]");
                if (!saveBar) {
                    return;
                }
                this.instances.set(form, new this(form, saveBar));
            }, this);

            this.bindGlobalListeners();
        }

        static mount(root = document) {
            this.initAll(root);
        }

        static instanceFor(form) {
            return form ? this.instances.get(form) || null : null;
        }

        static firstDirtyInstance() {
            return Array.from(this.instances.values()).find(function (instance) {
                return instance.shouldGuardNavigation();
            }) || null;
        }

        static bindGlobalListeners() {
            if (this.listenerRemovers.length > 0) {
                return;
            }

            this.listenerRemovers = [
                EventHelper.on(document, "click", this.handleDiscard.bind(this)),
                EventHelper.on(document, "click", (event) => { void this.handleNavigationClick(event); }),
                EventHelper.on(document, "lemonade:action:success", this.handleActionSuccess.bind(this)),
                EventHelper.on(document, "lemonade:action:navigate", this.handleActionNavigation.bind(this)),
                EventHelper.on(document, "lemonade:dirty-state:resync", this.handleResync.bind(this)),
                EventHelper.on(document, "lemonade:dirty-state:navigation-request", this.handleNavigationRequest.bind(this)),
                EventHelper.on(document, "keydown", (event) => { void this.handleReloadShortcut(event); }),
                EventHelper.on(window, "beforeunload", this.handleBeforeUnload.bind(this)),
            ];
        }

        static destroy(root = document) {
            const forms = root.matches && root.matches("form[data-lemonade-editor-form]") ? [root] : [];
            forms.push(...DomHelper.queryAll("form[data-lemonade-editor-form]", root));
            forms.forEach(function (form) {
                this.instanceFor(form)?.destroy();
            }, this);

            if (this.instances.size > 0) {
                return;
            }

            this.listenerRemovers.forEach(function (remove) { remove(); });
            this.listenerRemovers = [];
        }

        static handleDiscard(event) {
            const action = DomHelper.closest(event.target, "[data-lemonade-editor-discard]");
            if (!action) {
                return;
            }
            event.preventDefault();
            const form = document.getElementById(action.getAttribute("data-lemonade-form"));
            const instance = this.instanceFor(form);
            if (instance) {
                instance.discard();
            }
        }

        static async handleNavigationClick(event) {
            if (event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            const link = DomHelper.closest(event.target, "a[href]");
            const instance = Array.from(this.instances.values()).find(function (candidate) {
                return candidate.shouldGuardNavigation();
            });
            if (!link || !instance || !Confirm || !this.isInternalNavigation(link)) {
                return;
            }

            event.preventDefault();
            await instance.requestNavigation(link, function () {
                window.location.assign(link.href);
            });
        }

        static async handleReloadShortcut(event) {
            if (event.defaultPrevented || !this.isReloadShortcut(event)) {
                return;
            }
            const instance = Array.from(this.instances.values()).find(function (candidate) {
                return candidate.shouldGuardNavigation();
            });
            if (!instance || !Confirm) {
                return;
            }

            event.preventDefault();
            await instance.requestNavigation(event.target || instance.form, function () {
                window.location.reload();
            });
        }

        static isReloadShortcut(event) {
            if (event.altKey || event.shiftKey) {
                return false;
            }
            const key = (event.key || "").toLowerCase();
            if (key === "f5") {
                return !event.ctrlKey && !event.metaKey;
            }

            return key === "r" && ((event.ctrlKey && !event.metaKey) || (event.metaKey && !event.ctrlKey));
        }

        static isInternalNavigation(link) {
            const target = (link.getAttribute("target") || "").toLowerCase();
            if (link.hasAttribute("download") || (target && target !== "_self")) {
                return false;
            }
            const href = link.getAttribute("href");
            if (!href || href.charAt(0) === "#") {
                return false;
            }
            const destination = new URL(link.href, window.location.href);
            const current = new URL(window.location.href);
            if (destination.origin !== current.origin) {
                return false;
            }

            return destination.pathname !== current.pathname || destination.search !== current.search;
        }

        static handleActionSuccess(event) {
            const instance = this.instanceFor(event.detail && event.detail.form);
            if (instance) {
                instance.resetBaseline();
            }
        }

        static handleActionNavigation(event) {
            const instance = this.instanceFor(event.detail && event.detail.form);
            if (instance) {
                instance.setDirty(false);
            }
        }

        static handleResync(event) {
            const form = event.detail && event.detail.form
                ? event.detail.form
                : DomHelper.closest(event.target, "form[data-lemonade-editor-form]");
            const instance = this.instanceFor(form);
            if (instance) {
                instance.updateDirty();
            }
        }

        static handleNavigationRequest(event) {
            const instance = this.instanceFor(DomHelper.closest(event.target, "form[data-lemonade-editor-form]"));
            if (!instance?.shouldGuardNavigation()) {
                return;
            }

            event.preventDefault();
            void instance.requestNavigation(event.detail?.trigger || instance.form, () => {}).then(function (confirmed) {
                if (typeof event.detail?.resolve === "function") {
                    event.detail.resolve(confirmed);
                }
            });
        }

        static handleBeforeUnload(event) {
            const hasDirtyForm = Array.from(this.instances.values()).some(function (instance) {
                return instance.shouldGuardNavigation();
            });
            if (!hasDirtyForm) {
                return;
            }
            event.preventDefault();
            event.returnValue = "";
        }
    };

export function mount(root = document) {
    FormDirtyState.mount(root);
}

export function destroy(root = document) {
    FormDirtyState.destroy(root);
}

FormDirtyState.instances = new Map();
FormDirtyState.listenerRemovers = [];
