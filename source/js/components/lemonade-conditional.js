/*!
 * Lemonade Conditional
 *
 * Toggles declarative conditional form panels.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
    export class Conditional {
        constructor(element) {
            this.element = element;
            this.controls = DomHelper.queryAll("[data-lemonade-conditional-control]", element);
            this.panels = DomHelper.queryAll("[data-lemonade-conditional-panel]", element);
        }

        init() {
            this.controls.forEach(function (control) {
                control.addEventListener("change", this.update.bind(this));
            }, this);
            this.update();
        }

        update() {
            const activeControl = this.controls.find(function (control) {
                return control.checked || (control.tagName === "SELECT" && control.value);
            });
            const activeValue = activeControl ? activeControl.value : "";

            this.panels.forEach(function (panel) {
                const isActive = panel.getAttribute("data-lemonade-conditional-panel") === activeValue;
                panel.hidden = !isActive;
                DomHelper.queryAll("input, select, textarea, button", panel).forEach(function (control) {
                    control.disabled = !isActive;
                });
            });

            this.controls.forEach(function (control) {
                const card = DomHelper.closest(control, ".form-check");
                if (card) {
                    card.classList.toggle("is-selected", control.value === activeValue);
                }
            });
        }

        static initAll(context) {
            this.mount(context || document);
        }

        static mount(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-conditional]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-conditional]", root));
            elements.forEach(function (element) {
                if (element.lemonadeConditional) {
                    return;
                }
                const conditional = new Conditional(element);
                conditional.init();
                element.lemonadeConditional = conditional;
            });
        }
    };

export function mount(root = document) {
    Conditional.mount(root);
}
