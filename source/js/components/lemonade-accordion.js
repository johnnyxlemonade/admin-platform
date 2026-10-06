/*!
 * Lemonade Accordion
 *
 * Manages accessible, independently expandable Admin panels.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
    export class Accordion {
        constructor(element) {
            this.element = element;
            this.trigger = element.querySelector("[data-lemonade-accordion-trigger]");
            this.panel = element.querySelector("[data-lemonade-accordion-panel]");
        }

        init() {
            if (!this.trigger || !this.panel || !this.panel.id) {
                return false;
            }

            this.trigger.setAttribute("aria-controls", this.panel.id);
            this.setExpanded(this.trigger.getAttribute("aria-expanded") === "true" && !this.panel.hidden, false);
            EventHelper.on(this.trigger, "click", this.toggle.bind(this));

            return true;
        }

        toggle() {
            this.setExpanded(!this.isExpanded(), true);
        }

        isExpanded() {
            return this.trigger.getAttribute("aria-expanded") === "true";
        }

        setExpanded(expanded, announce) {
            this.trigger.setAttribute("aria-expanded", expanded ? "true" : "false");
            this.panel.hidden = !expanded;
            this.element.classList.toggle("is-expanded", expanded);
            if (announce) {
                this.element.dispatchEvent(new CustomEvent("lemonade:accordion:change", {
                    bubbles: true,
                    detail: { expanded: expanded, trigger: this.trigger, panel: this.panel }
                }));
            }
        }

        static initAll(context) {
            DomHelper.queryAll("[data-lemonade-accordion]", context || document).forEach(function (element) {
                if (element.lemonadeAccordion) {
                    return;
                }

                const accordion = new Accordion(element);
                if (accordion.init()) {
                    element.lemonadeAccordion = accordion;
                }
            });
        }
    };
