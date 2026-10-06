/*!
 * Lemonade DOM Helper
 *
 * Provides small DOM utility methods.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
export class DomHelper {
        static queryAll(selector, context) {
            return Array.prototype.slice.call((context || document).querySelectorAll(selector));
        }

        static closest(element, selector) {
            return element ? element.closest(selector) : null;
        }

        static restoreFocusOutside(container, opener) {
            const activeElement = document.activeElement;
            if (!activeElement || !container || !container.contains(activeElement)) {
                return true;
            }

            if (this.focusOutside(container, opener)) {
                return true;
            }

            if (typeof activeElement.blur === "function") {
                activeElement.blur();
            }

            const fallbacks = [
                document.querySelector("[data-lemonade-admin-shell] main"),
                document.querySelector("[data-lemonade-grid]"),
                document.querySelector("[data-lemonade-admin-shell]"),
                document.body,
                document.documentElement || null,
            ];
            for (const fallback of fallbacks) {
                if (this.focusOutside(container, fallback)) {
                    return true;
                }
            }

            if (container.contains(document.activeElement) && typeof document.activeElement.blur === "function") {
                document.activeElement.blur();
            }

            return !container.contains(document.activeElement);
        }

        static focusOutside(container, candidate) {
            if (!candidate || candidate === container || container.contains(candidate) || typeof candidate.focus !== "function") {
                return false;
            }

            const addedTabIndex = typeof candidate.hasAttribute === "function"
                && typeof candidate.setAttribute === "function"
                && !candidate.hasAttribute("tabindex");
            if (addedTabIndex) {
                candidate.setAttribute("tabindex", "-1");
            }
            candidate.focus({ preventScroll: true });
            const focusedOutside = !container.contains(document.activeElement);
            if (addedTabIndex && typeof candidate.removeAttribute === "function") {
                candidate.removeAttribute("tabindex");
            }

            return focusedOutside;
        }
}
