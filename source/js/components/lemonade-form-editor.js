/*!
 * Lemonade Form Editor
 *
 * Owns submit events for shared admin editor forms.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { Action } from "./lemonade-action.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
    export class FormEditor {
        static initAll(context) {
            this.syncEditorVersions(context);
            if (this.isBound) {
                return;
            }

            this.isBound = true;
            EventHelper.delegate(document, "click", "[data-lemonade-action][data-lemonade-form]", this.handleClick.bind(this));
            EventHelper.delegate(document, "submit", "form[data-lemonade-editor-form]", this.handleSubmit.bind(this));
            EventHelper.on(window, "pageshow", () => this.syncEditorVersions(document));
        }

        static syncEditorVersions(context) {
            const root = context || document;
            const fields = root instanceof window.HTMLInputElement && root.hasAttribute("data-lemonade-editor-version")
                ? [root]
                : Array.prototype.slice.call(root.querySelectorAll("[data-lemonade-editor-version]"));

            fields.forEach(function (field) {
                const version = Number(field.getAttribute("data-lemonade-editor-version-value"));
                if (Number.isSafeInteger(version) && version > 0) {
                    field.value = String(version);
                }
            });
        }

        static handleClick(event, action) {
            const form = this.formFor(action);
            if (!this.isEditorForm(form)) {
                return;
            }

            this.submit(form, action, event);
        }

        static handleSubmit(event, form) {
            if (!this.isEditorForm(form)) {
                return;
            }

            this.submit(form, this.actionFor(form), event);
        }

        static submit(form, action, event) {
            event.preventDefault();
            if (typeof event.stopImmediatePropagation === "function") {
                event.stopImmediatePropagation();
            }
            if (form.hasAttribute("data-lemonade-submitting") || !action || action.disabled) {
                return;
            }

            void new Action(action).execute(event);
        }

        static formFor(action) {
            if (!action) {
                return null;
            }

            const formId = action.getAttribute("data-lemonade-form");

            return formId ? document.getElementById(formId) : null;
        }

        static actionFor(form) {
            return document.querySelector(`[data-lemonade-action][data-lemonade-form="${form.id}"]`);
        }

        static isEditorForm(form) {
            return form !== null && form.hasAttribute("data-lemonade-editor-form");
        }

        static mount(root = document) {
            this.initAll(root);
        }
    };

export function mount(root = document) {
    FormEditor.mount(root);
}
