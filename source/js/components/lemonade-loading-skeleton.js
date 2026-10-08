/*!
 * Lemonade Loading Skeleton
 *
 * Renders shared list and statistic loading placeholders for admin content.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
export class LoadingSkeleton {
        static markup({ variant = "list", rows = 4, text = "Loading…", textKey = "" } = {}) {
            const normalizedVariant = variant === "stat" ? "stat" : "list";
            const normalizedRows = Number.isInteger(rows) && rows > 0 ? rows : 4;
            const translation = typeof textKey === "string" && textKey !== "" ? ' data-lemonade-i18n="' + this.escapeAttribute(textKey) + '"' : "";
            const status = '<span class="visually-hidden"' + translation + ">" + this.escape(text) + "</span>";

            if (normalizedVariant === "stat") {
                return '<div class="lm-loading-skeleton lm-loading-skeleton--stat" role="status">' + status + '<span class="lm-loading-skeleton-block lm-loading-skeleton-block--metric" aria-hidden="true"></span><span class="lm-loading-skeleton-block lm-loading-skeleton-block--label" aria-hidden="true"></span></div>';
            }

            return '<div class="lm-loading-skeleton lm-loading-skeleton--list" data-lm-loading-skeleton-rows="' + normalizedRows + '" role="status">' + status + Array.from({ length: normalizedRows }, function () { return '<div class="lm-loading-skeleton-row" aria-hidden="true"><span class="lm-loading-skeleton-avatar"></span><span class="lm-loading-skeleton-copy"><span class="lm-loading-skeleton-block"></span><span class="lm-loading-skeleton-block lm-loading-skeleton-block--short"></span></span><span class="lm-loading-skeleton-block lm-loading-skeleton-block--trailing"></span></div>'; }).join("") + "</div>";
        }

        static escape(value) {
            const element = document.createElement("span");
            element.textContent = String(value);

            return element.innerHTML;
        }

        static escapeAttribute(value) {
            return this.escape(value).replace(/"/g, "&quot;");
        }
}
