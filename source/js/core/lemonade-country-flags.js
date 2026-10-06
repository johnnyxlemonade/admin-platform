/*!
 * Lemonade Country Flags
 *
 * Applies the country-flag emoji polyfill to the admin UI.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { polyfillCountryFlagEmojis } from "country-flag-emoji-polyfill";

polyfillCountryFlagEmojis(
    "Twemoji Country Flags",
    new URL(/* @vite-ignore */ "../fonts/flags/TwemojiCountryFlags.woff2", import.meta.url).pathname,
);
