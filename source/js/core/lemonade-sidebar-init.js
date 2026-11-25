/*!
 * Lemonade Sidebar Init
 *
 * Applies the stored sidebar state before rendering.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
(function (document, window) {
    "use strict";

    const html = document.documentElement;
    const storageKey = html.getAttribute("data-lemonade-sidebar-storage-key") || "lemonade-admin-sidebar";
    let storedState;

    try {
        storedState = JSON.parse(window.localStorage.getItem(storageKey));
    } catch (error) {
        storedState = null;
    }

    const isMobile = typeof window.matchMedia === "function"
        && window.matchMedia("(max-width: 767.98px)").matches;

    html.setAttribute("data-lemonade-sidebar-state", storedState === true || isMobile ? "collapsed" : "expanded");
})(document, window);
