/*!
 * Lemonade Theme Init
 *
 * Records JavaScript capability and applies the stored theme before rendering.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
(function (document, window) {
    "use strict";

    document.documentElement.classList.replace("no-js", "js");

    const storageKey = "lemonade.admin.theme";
    let storedPreference;

    try {
        storedPreference = JSON.parse(window.localStorage.getItem(storageKey));
    } catch (error) {
        storedPreference = null;
    }

    const preference = storedPreference === "light" || storedPreference === "dark" || storedPreference === "system"
        ? storedPreference
        : "system";
    const systemThemeIsDark = typeof window.matchMedia === "function"
        && window.matchMedia("(prefers-color-scheme: dark)").matches;
    const resolvedTheme = preference === "system"
        ? systemThemeIsDark ? "dark" : "light"
        : preference;

    document.documentElement.setAttribute("data-lemonade-theme-preference", preference);
    document.documentElement.setAttribute("data-bs-theme", resolvedTheme);
})(document, window);
