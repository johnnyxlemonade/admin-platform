/*!
 * Lemonade Notification Audience
 *
 * Coordinates recipient audience selection for notifications.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
    export class NotificationAudience {
        constructor(element) {
            this.element = element;
            this.radios = Array.from(element.querySelectorAll("[data-lemonade-notification-audience-type]"));
            this.targets = Array.from(element.querySelectorAll("[data-lemonade-notification-audience-target]"));
        }

        init() {
            this.radios.forEach(function (radio) {
                radio.addEventListener("change", this.sync.bind(this));
            }, this);
            this.sync();
        }

        sync() {
            const selected = this.radios.find(function (radio) { return radio.checked; });
            const type = selected ? selected.value : "roles";
            this.targets.forEach(function (target) {
                const active = target.getAttribute("data-lemonade-notification-audience-target") === type;
                target.hidden = !active;
                target.querySelectorAll("select, input, textarea").forEach(function (control) {
                    control.disabled = !active;
                    if (control.lemonadeSelect) {
                        control.lemonadeSelect.render();
                    }
                    if (!active && control.tagName === "SELECT") {
                        Array.from(control.options).forEach(function (option) { option.selected = false; });
                    }
                });
            });
        }

        static initAll(context) {
            DomHelper.queryAll("[data-lemonade-notification-audience]", context || document).forEach(function (element) {
                if (element.lemonadeNotificationAudience) {
                    return;
                }

                const audience = new NotificationAudience(element);
                audience.init();
                element.lemonadeNotificationAudience = audience;
            });
        }

        static mount(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-notification-audience]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-notification-audience]", root));
            elements.forEach(function (element) {
                if (element.lemonadeNotificationAudience) {
                    return;
                }

                const audience = new NotificationAudience(element);
                audience.init();
                element.lemonadeNotificationAudience = audience;
            });
        }
    };

export function mount(root = document) {
    NotificationAudience.mount(root);
}
