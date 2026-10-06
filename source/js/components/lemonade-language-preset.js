/*!
 * Lemonade Language Preset
 *
 * Prefills language editor fields from a selected canonical language preset.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
class LanguagePreset {
    constructor(element) {
        this.element = element;
        this.values = this.parseValues(element.getAttribute("data-lemonade-language-preset-values"));
        this.onChange = this.handleChange.bind(this);
    }

    init() {
        this.element.addEventListener("change", this.onChange);
    }

    destroy() {
        this.element.removeEventListener("change", this.onChange);
    }

    handleChange() {
        const preset = this.values[this.element.value];
        if (!preset) {
            return;
        }

        const form = this.element.form;
        this.setFieldValue(form, "code", this.element.value);
        this.setFieldValue(form, "name", preset.name);
        this.setFieldValue(form, "flag_code", preset.flagCode || "");
    }

    setFieldValue(form, fieldName, value) {
        const field = form?.querySelector(`[data-lemonade-field="${fieldName}"]`);
        if (!field) {
            return;
        }

        field.value = value;
        field.dispatchEvent(new Event("change", { bubbles: true }));
    }

    parseValues(serializedValues) {
        try {
            const values = JSON.parse(serializedValues || "{}");

            return values && typeof values === "object" ? values : {};
        } catch (error) {
            return {};
        }
    }

    static mount(root = document) {
        const elements = root.matches?.("[data-lemonade-language-preset-select]")
            ? [root]
            : [];
        elements.push(...root.querySelectorAll?.("[data-lemonade-language-preset-select]") || []);

        elements.forEach((element) => {
            if (element.lemonadeLanguagePreset) {
                return;
            }

            element.lemonadeLanguagePreset = new LanguagePreset(element);
            element.lemonadeLanguagePreset.init();
        });
    }

    static destroy(root = document) {
        const elements = root.matches?.("[data-lemonade-language-preset-select]")
            ? [root]
            : [];
        elements.push(...root.querySelectorAll?.("[data-lemonade-language-preset-select]") || []);

        elements.forEach((element) => {
            element.lemonadeLanguagePreset?.destroy();
            delete element.lemonadeLanguagePreset;
        });
    }
}

export function mount(root = document) {
    LanguagePreset.mount(root);
}

export function destroy(root = document) {
    LanguagePreset.destroy(root);
}
