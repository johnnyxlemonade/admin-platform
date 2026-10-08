/*!
 * Lemonade Event Helper
 *
 * Provides shared event, delegation and scheduling utilities.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
export class EventHelper {
        static animationFrame(callback) {
            let frame = null;
            let context;
            let argumentsList;

            const scheduled = function () {
                context = this;
                argumentsList = arguments;
                if (frame !== null) {
                    return;
                }

                frame = window.requestAnimationFrame(function () {
                    frame = null;
                    callback.apply(context, argumentsList);
                });
            };

            scheduled.cancel = function () {
                if (frame === null) {
                    return;
                }

                window.cancelAnimationFrame(frame);
                frame = null;
            };

            return scheduled;
        }

        static debounce(callback, delay) {
            let timeoutId;
            let context;
            let argumentsList;

            const debounced = function () {
                context = this;
                argumentsList = arguments;

                window.clearTimeout(timeoutId);
                timeoutId = window.setTimeout(function () {
                    callback.apply(context, argumentsList);
                    timeoutId = undefined;
                }, delay);
            };

            debounced.cancel = function () {
                window.clearTimeout(timeoutId);
                timeoutId = undefined;
            };

            debounced.flush = function () {
                if (timeoutId === undefined) {
                    return;
                }
                window.clearTimeout(timeoutId);
                timeoutId = undefined;
                callback.apply(context, argumentsList);
            };

            return debounced;
        }

        static on(element, eventName, callback, options = undefined) {
            if (!element) {
                return function () {};
            }

            element.addEventListener(eventName, callback, options);

            return function () {
                element.removeEventListener(eventName, callback, options);
            };
        }

        static delegate(element, eventName, selector, callback, options = undefined) {
            return this.on(element, eventName, function (event) {
                const target = event.target;
                const matched = target && typeof target.closest === "function" ? target.closest(selector) : null;
                if (!matched || (matched !== element && !element.contains(matched))) {
                    return;
                }

                callback(event, matched);
            }, options);
        }
}
