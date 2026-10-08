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

    html.setAttribute("data-lemonade-sidebar-state", storedState === true ? "collapsed" : "expanded");
})(document, window);
