/*!
 * Lemonade Bootstrap Icons
 *
 * Formats Bootstrap Icons identifiers used by the Admin runtime.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
const identifierPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const fallbackIdentifier = "circle";

export const BootstrapIcons = Object.freeze({
    isIdentifier(identifier) {
        return typeof identifier === "string" && identifierPattern.test(identifier);
    },

    identifier(identifier, fallback = fallbackIdentifier) {
        const safeFallback = this.isIdentifier(fallback) ? fallback : fallbackIdentifier;

        return this.isIdentifier(identifier) ? identifier : safeFallback;
    },

    className(identifier, fallback) {
        return "bi bi-" + this.identifier(identifier, fallback);
    },

    set(element, identifier, fallback) {
        if (!element) {
            return element;
        }

        const oldIconClasses = [];
        element.classList.forEach(function (className) {
            if (className === "bi" || className.indexOf("bi-") === 0) {
                oldIconClasses.push(className);
            }
        });
        if (oldIconClasses.length > 0) {
            element.classList.remove(...oldIconClasses);
        }
        element.classList.add("bi", "bi-" + this.identifier(identifier, fallback));

        return element;
    }
});
