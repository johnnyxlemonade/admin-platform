/*!
 * Lemonade Password Toggle
 *
 * Toggles password field visibility.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
    export class PasswordToggle {
        static initAll(context) {
            if (!this.isBound) {
                this.isBound = true;
                EventHelper.delegate(document, "click", "[data-lemonade-password-toggle]", function (event, toggle) {
                    event.preventDefault();
                    PasswordToggle.toggle(toggle);
                });
                EventHelper.on(document, "lemonade:locale:changed", function () {
                    PasswordToggle.syncAll();
                });
            }

            this.syncAll(context || document);
        }

        static mount(root = document) {
            this.initAll(root);
        }

        static toggle(toggle) {
            const input = this.getInput(toggle);
            if (!input) {
                return;
            }

            input.type = input.type === "password" ? "text" : "password";
            this.sync(toggle, input);
        }

        static getInput(toggle) {
            const field = DomHelper.closest(toggle, ".lm-auth-field");
            return field ? field.querySelector("[data-lemonade-password-input]") : null;
        }

        static syncAll(context) {
            const root = context || document;
            const toggles = root.matches && root.matches("[data-lemonade-password-toggle]") ? [root] : [];
            toggles.push(...DomHelper.queryAll("[data-lemonade-password-toggle]", root));
            toggles.forEach(function (toggle) {
                const input = PasswordToggle.getInput(toggle);
                if (input) {
                    PasswordToggle.sync(toggle, input);
                }
            });
        }

        static sync(toggle, input) {
            const isVisible = input.type === "text";
            const icon = toggle.querySelector(".bi");
            const key = toggle.getAttribute(isVisible ? "data-lemonade-password-hide-key" : "data-lemonade-password-show-key") || (isVisible ? "auth.password.hide" : "auth.password.show");
            toggle.setAttribute("aria-pressed", String(isVisible));
            toggle.setAttribute("aria-label", I18n.t(key));
            if (icon) {
                BootstrapIcons.set(icon, isVisible ? "eye-slash" : "eye");
            }
        }
    };

export function mount(root = document) {
    PasswordToggle.mount(root);
}
