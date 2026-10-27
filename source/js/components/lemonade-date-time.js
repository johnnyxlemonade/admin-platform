/*!
 * Lemonade Date and Time
 *
 * Enhances Admin date fields while preserving their submitted values.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import flatpickr from "flatpickr";
import { Czech } from "flatpickr/dist/l10n/cs.js";
import { I18n } from "../core/lemonade-i18n.js";
import "../../scss/components/_date-time.scss";

const selector = "input[data-lemonade-date-time]";

function isCzech() {
    return I18n.getLocale() === "cs";
}

function displayFormat(dateTime) {
    return (isCzech() ? "d.m.Y" : "Y-m-d") + (dateTime ? " H:i" : "");
}

class DateTime {
    constructor(input) {
        this.input = input;
        this.dateTime = input.getAttribute("data-lemonade-date-time") === "datetime-local";
        this.readOnly = input.readOnly;
        this.form = input.form;
        this.dialog = input.closest("[data-lemonade-modal-root]");
        this.floatingRoot = this.dialog?.querySelector("[data-lemonade-modal-floating-root]");
        this.label = Array.from(input.closest(".lm-form-group")?.querySelectorAll("label") || [])
            .find((label) => label.htmlFor === input.id);
        this.handleLocaleChange = this.handleLocaleChange.bind(this);
        this.handleScroll = this.handleScroll.bind(this);
        this.syncFromInput = this.syncFromInput.bind(this);
        this.handleActionSuccess = this.handleActionSuccess.bind(this);
    }

    mount() {
        this.picker = flatpickr(this.input, {
            altInput: true,
            altFormat: displayFormat(this.dateTime),
            dateFormat: this.dateTime ? "Y-m-d\\TH:i" : "Y-m-d",
            enableTime: this.dateTime,
            time_24hr: true,
            allowInput: true,
            disableMobile: true,
            clickOpens: !this.readOnly && !this.input.disabled,
            locale: isCzech() ? Czech : "default",
            appendTo: this.floatingRoot || document.body,
            position: (picker) => this.position(picker),
            minDate: this.input.getAttribute("min") || undefined,
            maxDate: this.input.getAttribute("max") || undefined,
        });

        if (!this.picker?.altInput) {
            return;
        }

        const visible = this.picker.altInput;
        visible.classList.add("lm-date-time-input");
        visible.readOnly = this.readOnly;
        visible.id = this.input.id ? `${this.input.id}-display` : "";
        if (this.label) this.label.htmlFor = visible.id;
        for (const attribute of ["aria-invalid", "aria-describedby", "aria-label"]) {
            if (this.input.hasAttribute(attribute)) visible.setAttribute(attribute, this.input.getAttribute(attribute));
        }
        this.picker.calendarContainer.classList.add("lm-date-time-calendar");
        this.validationObserver = new MutationObserver(() => this.syncValidation());
        this.validationObserver.observe(this.input, { attributes: true, attributeFilter: ["class", "aria-invalid"] });
        this.syncValidation();
        document.addEventListener("lemonade:locale:changed", this.handleLocaleChange);
        document.addEventListener("scroll", this.handleScroll, true);
        document.addEventListener("lemonade:action:success", this.handleActionSuccess);
        this.form?.addEventListener("lemonade:dirty-state:discard", this.syncFromInput);
    }

    handleLocaleChange() {
        this.picker.set("locale", isCzech() ? Czech : "default");
        this.picker.set("altFormat", displayFormat(this.dateTime));
        this.picker.altInput.value = this.picker.selectedDates.length
            ? this.picker.formatDate(this.picker.selectedDates[0], this.picker.config.altFormat)
            : "";
    }

    handleScroll() {
        if (this.picker.isOpen) this.picker._positionCalendar();
    }

    syncFromInput() {
        this.picker.setDate(this.input.value, false, this.picker.config.dateFormat);
    }

    handleActionSuccess(event) {
        if (this.form && event.detail?.form === this.form) this.syncFromInput();
    }

    syncValidation() {
        const visible = this.picker.altInput;
        visible.classList.toggle("is-invalid", this.input.classList.contains("is-invalid"));
        if (this.input.hasAttribute("aria-invalid")) {
            visible.setAttribute("aria-invalid", this.input.getAttribute("aria-invalid"));
        } else {
            visible.removeAttribute("aria-invalid");
        }
    }

    position(picker) {
        const calendar = picker.calendarContainer;
        const input = picker.altInput.getBoundingClientRect();
        const width = calendar.offsetWidth;
        const height = calendar.offsetHeight;
        const above = window.innerHeight - input.bottom < height && input.top > window.innerHeight - input.bottom;
        const top = Math.max(8, Math.min(above ? input.top - height - 2 : input.bottom + 2, window.innerHeight - height - 8));
        const left = Math.max(8, Math.min(input.left, window.innerWidth - width - 8));
        const offsetX = this.floatingRoot ? 0 : window.scrollX;
        const offsetY = this.floatingRoot ? 0 : window.scrollY;
        calendar.classList.toggle("arrowTop", !above);
        calendar.classList.toggle("arrowBottom", above);
        calendar.classList.toggle("arrowRight", left < input.left);
        calendar.style.top = `${top + offsetY}px`;
        calendar.style.left = `${left + offsetX}px`;
        calendar.style.right = "auto";
    }

    destroy() {
        if (!this.picker) return;
        document.removeEventListener("lemonade:locale:changed", this.handleLocaleChange);
        document.removeEventListener("scroll", this.handleScroll, true);
        document.removeEventListener("lemonade:action:success", this.handleActionSuccess);
        this.form?.removeEventListener("lemonade:dirty-state:discard", this.syncFromInput);
        this.validationObserver?.disconnect();
        if (this.label) this.label.htmlFor = this.input.id;
        this.picker.destroy();
        this.input.readOnly = this.readOnly;
    }
}

export function mount(root = document) {
    const inputs = root.matches?.(selector) ? [root] : [];
    inputs.push(...root.querySelectorAll?.(selector) || []);
    inputs.forEach((input) => {
        if (input.lemonadeDateTime) return;
        input.lemonadeDateTime = new DateTime(input);
        input.lemonadeDateTime.mount();
    });
}

export function destroy(root = document) {
    const inputs = root.matches?.(selector) ? [root] : [];
    inputs.push(...root.querySelectorAll?.(selector) || []);
    inputs.forEach((input) => {
        input.lemonadeDateTime?.destroy();
        delete input.lemonadeDateTime;
    });
}
