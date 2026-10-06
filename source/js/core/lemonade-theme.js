/*!
 * Lemonade Theme
 *
 * Manages the admin theme preference and its resolved theme.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { StorageHelper } from "./lemonade-storage-helper.js";

export class Theme {
        static init() {
            if (this.initialized) {
                this.syncControls();
                return;
            }

            this.initialized = true;
            this.applyPreference(this.resolveInitialPreference());
            this.bindControls();
            this.bindSystemThemeListener();
            document.addEventListener("lemonade:locale:changed", function () {
                Theme.syncControls();
            });
        }

        static get() {
            return this.getResolvedTheme();
        }

        static getPreference() {
            return this.normalizePreference(document.documentElement.getAttribute("data-lemonade-theme-preference"));
        }

        static getResolvedTheme() {
            return this.normalizeResolvedTheme(document.documentElement.getAttribute("data-bs-theme"));
        }

        static set(preference) {
            this.setPreference(preference);
        }

        static setPreference(preference) {
            const nextPreference = this.normalizePreference(preference);
            const theme = this.applyPreference(nextPreference);

            StorageHelper.set(this.storageKey, nextPreference);
            document.dispatchEvent(new CustomEvent("lemonade:theme:changed", {
                detail: {
                    preference: nextPreference,
                    theme,
                },
            }));
        }

        static toggle() {
            this.setPreference(this.getResolvedTheme() === "dark" ? "light" : "dark");
        }

        static resolveInitialPreference() {
            return this.normalizePreference(document.documentElement.getAttribute("data-lemonade-theme-preference"));
        }

        static normalizePreference(preference) {
            return preference === "light" || preference === "dark" || preference === "system"
                ? preference
                : "system";
        }

        static normalizeResolvedTheme(theme) {
            return theme === "dark" ? "dark" : "light";
        }

        static resolveTheme(preference) {
            if (preference !== "system") {
                return preference;
            }

            return this.systemThemeQuery().matches ? "dark" : "light";
        }

        static applyPreference(preference) {
            const nextPreference = this.normalizePreference(preference);
            const theme = this.resolveTheme(nextPreference);

            document.documentElement.setAttribute("data-lemonade-theme-preference", nextPreference);
            document.documentElement.setAttribute("data-bs-theme", theme);
            this.syncControls();

            return theme;
        }

        static systemThemeQuery() {
            if (this.systemThemeMediaQuery === null) {
                this.systemThemeMediaQuery = typeof window.matchMedia === "function"
                    ? window.matchMedia("(prefers-color-scheme: dark)")
                    : {
                        matches: false,
                        addListener() {},
                    };
            }

            return this.systemThemeMediaQuery;
        }

        static bindControls() {
            document.addEventListener("click", function (event) {
                const option = event.target.closest("[data-lemonade-theme-option]");
                if (!option) {
                    return;
                }

                event.preventDefault();
                Theme.setPreference(option.getAttribute("data-lemonade-theme-option"));
                option.closest("[data-lemonade-dropdown]")?.lemonadeDropdown?.close("select");
            });

            document.addEventListener("keydown", function (event) {
                const menu = event.target.closest("[role=\"menu\"]");
                if (!menu || !["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) {
                    return;
                }

                const items = Array.from(menu.querySelectorAll("[role=\"menuitemradio\"]"));
                const currentIndex = items.indexOf(document.activeElement);
                const nextIndex = event.key === "Home" ? 0 : event.key === "End" ? items.length - 1 : (currentIndex + (event.key === "ArrowDown" ? 1 : -1) + items.length) % items.length;

                event.preventDefault();
                items[nextIndex]?.focus();
            });
        }

        static bindSystemThemeListener() {
            const mediaQuery = this.systemThemeQuery();
            const onChange = function () {
                if (Theme.getPreference() === "system") {
                    Theme.applyPreference("system");
                }
            };

            if (typeof mediaQuery.addEventListener === "function") {
                mediaQuery.addEventListener("change", onChange);

                return;
            }

            mediaQuery.addListener(onChange);
        }

        static syncControls() {
            const activePreference = this.getPreference();

            document.querySelectorAll("[data-lemonade-theme-option]").forEach(function (option) {
                const active = option.getAttribute("data-lemonade-theme-option") === activePreference;
                const check = option.querySelector("[data-lemonade-theme-check]");

                option.setAttribute("aria-checked", String(active));
                option.classList.toggle("is-active", active);

                if (check) {
                    check.hidden = !active;
                }
            });
        }
}

Theme.storageKey = "lemonade.admin.theme";
Theme.initialized = false;
Theme.systemThemeMediaQuery = null;
