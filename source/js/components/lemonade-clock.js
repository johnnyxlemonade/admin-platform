/*!
 * Lemonade Clock
 *
 * Updates live clock display elements.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
    export class Clock {
        constructor(element) {
            this.element = element;
            this.timeZone = element.getAttribute("data-lemonade-time-zone") || undefined;
            this.format = element.getAttribute("data-lemonade-clock-format") || "time";
            this.timeoutId = null;
            this.intervalId = null;
            this.handleLocaleChange = this.update.bind(this);
        }

        init() {
            this.update();
            this.scheduleUpdates();
            document.addEventListener("lemonade:locale:changed", this.handleLocaleChange);
        }

        getLocale() {
            return I18n && typeof I18n.getLocale === "function"
                ? I18n.getLocale()
                : document.documentElement.lang || undefined;
        }

        update() {
            const now = new Date();
            const options = this.format === "time"
                ? { hour: "2-digit", minute: "2-digit", second: "2-digit", hourCycle: "h23" }
                : {};

            if (this.timeZone) {
                options.timeZone = this.timeZone;
            }

            try {
                this.element.textContent = new window.Intl.DateTimeFormat(this.getLocale(), options).format(now);
            } catch (error) {
                this.element.textContent = now.toLocaleTimeString();
            }

            this.element.setAttribute("datetime", now.toISOString());
        }

        scheduleUpdates() {
            const self = this;
            const delay = 1000 - (Date.now() % 1000);

            this.timeoutId = window.setTimeout(function () {
                self.update();
                self.intervalId = window.setInterval(function () {
                    self.update();
                }, 1000);
            }, delay);
        }

        static initAll(context) {
            const scope = context || document;

            DomHelper.queryAll("[data-lemonade-clock]", scope).forEach(function (element) {
                if (element.dataset.lemonadeClockInitialized === "true") {
                    return;
                }

                element.dataset.lemonadeClockInitialized = "true";
                element.lemonadeClock = new Clock(element);
                element.lemonadeClock.init();
            });
        }

        static mount(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-clock]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-clock]", root));
            elements.forEach(function (element) {
                if (element.dataset.lemonadeClockInitialized === "true") {
                    return;
                }
                element.dataset.lemonadeClockInitialized = "true";
                new Clock(element).init();
            });
        }

        static destroy(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-clock]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-clock]", root));
            elements.forEach(function (element) {
                const clock = element.lemonadeClock;
                if (clock) {
                    window.clearTimeout(clock.timeoutId);
                    window.clearInterval(clock.intervalId);
                    document.removeEventListener("lemonade:locale:changed", clock.handleLocaleChange);
                    delete element.lemonadeClock;
                }
                delete element.dataset.lemonadeClockInitialized;
            });
        }
    };

export function mount(root = document) {
    Clock.mount(root);
}

export function destroy(root = document) {
    Clock.destroy(root);
}
