/*!
 * Lemonade Storage Helper
 *
 * Provides safe browser storage access.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
export class StorageHelper {
        static get(key) {
            try {
                const value = window.localStorage.getItem(key);
                return value === null ? null : JSON.parse(value);
            } catch (error) {
                return null;
            }
        }

        static set(key, value) {
            try {
                window.localStorage.setItem(key, JSON.stringify(value));
            } catch (error) {
                return false;
            }

            return true;
        }

        static remove(key) {
            try {
                window.localStorage.removeItem(key);
            } catch (error) {
                return false;
            }

            return true;
        }
}
