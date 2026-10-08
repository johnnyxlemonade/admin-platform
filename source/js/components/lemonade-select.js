/*!
 * Lemonade Select
 *
 * Enhances declarative select controls with local and remote options.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
    export class Select {
        constructor(element) {
            this.element = element;
            this.sourceType = element.getAttribute("data-lemonade-option-source") || "static";
            this.source = element.getAttribute("data-lemonade-source");
            this.searchParam = element.getAttribute("data-lemonade-search-param") || "q";
            this.minLength = Number(element.getAttribute("data-lemonade-min-length") || 0);
            this.searchable = this.sourceType === "async" || element.hasAttribute("data-lemonade-searchable");
            this.allowCreate = element.multiple && element.hasAttribute("data-lemonade-allow-create");
            this.createLabelKey = element.getAttribute("data-lemonade-create-label-key") || "admin.select.create";
            this.noResultsKey = element.getAttribute("data-lemonade-no-results-key") || "admin.select.no_results";
            this.page = 1;
            this.query = "";
            this.requestId = 0;
            this.loaded = this.sourceType !== "async";
            this.localOptions = Array.from(element.options).map(function (option) { return { value: option.value, label: option.text }; });
            this.resultOptions = this.localOptions;
            this.stateKey = this.resultOptions.length === 0 ? "admin.select.no_options" : "";
            this.controller = null;
            this.floatingRoot = element.closest("[data-lemonade-select-floating-root]");
            this.dropdownContainer = this.floatingRoot
                || element.closest("[data-lemonade-modal-root]")?.querySelector("[data-lemonade-modal-floating-root]")
                || document.body;
            this.localPositioning = this.floatingRoot?.getAttribute("data-lemonade-select-floating-root") === "local";
            this.searchDebounce = null;
            this.listenerRemovers = [];
            this.destroyed = false;
        }

        init() {
            this.element.classList.add("lm-select-native");
            this.root = document.createElement("div");
            this.root.className = "lm-select";
            this.element.parentNode.insertBefore(this.root, this.element);
            this.root.appendChild(this.element);

            this.trigger = document.createElement("button");
            this.trigger.type = "button";
            this.trigger.className = "form-select lm-select-trigger";
            this.trigger.setAttribute("aria-haspopup", "listbox");
            this.trigger.setAttribute("aria-expanded", "false");
            this.root.appendChild(this.trigger);

            this.dropdown = document.createElement("div");
            this.dropdown.className = "lm-select-dropdown lm-select-dropdown--floating";
            this.dropdown.classList.toggle("lm-select-dropdown--local", this.localPositioning);
            this.dropdown.hidden = true;
            if (this.searchable) {
                this.searchInput = document.createElement("input");
                this.searchInput.type = "search";
                this.searchInput.className = "form-control lm-select-search";
                const key = this.element.getAttribute("data-lemonade-search-placeholder-key") || "admin.select.search";
                this.searchInput.setAttribute("placeholder", I18n.t(key));
                this.searchInput.setAttribute("data-lemonade-i18n-placeholder", key);
                this.dropdown.appendChild(this.searchInput);
                this.searchDebounce = EventHelper.debounce(this.handleInput.bind(this), 250);
                this.listenerRemovers.push(
                    EventHelper.on(this.searchInput, "input", this.searchDebounce),
                    EventHelper.on(this.searchInput, "keydown", this.searchKeydown.bind(this)),
                );
            }
            this.results = document.createElement("div");
            this.results.className = "lm-select-results";
            this.results.setAttribute("role", "listbox");
            if (this.element.multiple) {
                this.results.setAttribute("aria-multiselectable", "true");
            }
            this.dropdown.appendChild(this.results);
            this.more = document.createElement("button");
            this.more.type = "button";
            this.more.className = "btn btn-light btn-sm lm-select-more";
            this.more.textContent = I18n.t("admin.datagrid.next");
            this.more.setAttribute("data-lemonade-i18n", "admin.datagrid.next");
            this.more.hidden = true;
            this.dropdown.appendChild(this.more);

            const viewportChange = EventHelper.animationFrame(this.handleViewportChange.bind(this));
            this.listenerRemovers.push(
                EventHelper.on(this.trigger, "click", this.toggle.bind(this)),
                EventHelper.on(this.trigger, "keydown", this.keydown.bind(this)),
                EventHelper.on(this.more, "click", this.nextPage.bind(this)),
                EventHelper.on(document, "click", this.closeOutside.bind(this)),
                EventHelper.on(window, "resize", viewportChange),
                EventHelper.on(document, "scroll", viewportChange, { capture: true }),
                viewportChange.cancel,
            );
            this.render();
        }

        toggle() { this.dropdown.hidden ? this.open() : this.close(); }
        open() {
            if (this.destroyed) {
                return;
            }

            this.mountDropdown();
            this.dropdown.hidden = false;
            this.root.classList.add("is-open");
            this.trigger.setAttribute("aria-expanded", "true");
            this.positionDropdown();
            if (this.searchable) {
                this.searchInput.focus();
            }
            if (this.sourceType === "async") {
                if (!this.loaded) { this.startSearch(); }
            }
        }
        close() {
            if (this.destroyed) {
                return;
            }

            this.dropdown.hidden = true;
            this.dropdown.remove();
            this.root.classList.remove("is-open");
            this.trigger.setAttribute("aria-expanded", "false");
        }
        closeOutside(event) {
            if (!this.root.contains(event.target) && !this.dropdown.contains(event.target)) {
                this.close();
            }
        }
        keydown(event) {
            if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                this.open();
            }
            if (event.key === "Escape") {
                this.close();
            }
        }

        searchKeydown(event) {
            if (event.key === "Escape") {
                event.preventDefault();
                this.close();
                this.trigger.focus();
                return;
            }
            if (event.key !== "Enter") {
                return;
            }

            event.preventDefault();
            const firstResult = this.results.querySelector("[data-lemonade-select-option]");
            if (firstResult) {
                firstResult.click();
                return;
            }
            if (this.canCreateValue()) {
                this.createValue();
            }
        }

        mountDropdown() {
            if (this.dropdown.parentNode !== this.dropdownContainer) {
                this.dropdownContainer.appendChild(this.dropdown);
            }
        }

        positionDropdown() {
            if (this.dropdown.hidden || !this.trigger) {
                return;
            }

            const gap = 4;
            const margin = 4;
            const triggerRect = this.trigger.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
            const dropdownHeight = this.dropdown.offsetHeight;
            const spaceBelow = viewportHeight - triggerRect.bottom - margin;
            const spaceAbove = triggerRect.top - margin;
            const openUpward = spaceBelow < dropdownHeight && spaceAbove > spaceBelow;
            const left = Math.max(margin, Math.min(triggerRect.left, viewportWidth - triggerRect.width - margin));
            const top = openUpward
                ? Math.max(margin, triggerRect.top - dropdownHeight - gap)
                : triggerRect.bottom + gap;
            const rootRect = this.localPositioning ? this.dropdownContainer.getBoundingClientRect() : null;
            const rootLeft = rootRect ? rootRect.left + this.dropdownContainer.clientLeft : 0;
            const rootTop = rootRect ? rootRect.top + this.dropdownContainer.clientTop : 0;

            this.dropdown.style.left = `${left - rootLeft}px`;
            this.dropdown.style.top = `${top - rootTop}px`;
            this.dropdown.style.width = `${triggerRect.width}px`;
            this.dropdown.classList.toggle("is-open-upward", openUpward);
        }

        handleViewportChange() {
            this.positionDropdown();
        }

        handleInput() {
            if (this.destroyed) {
                return;
            }

            this.startSearch();
        }
        startSearch() {
            this.query = this.searchInput.value.trim();
            if (this.sourceType === "static") {
                this.filterLocalOptions(this.query);
                return;
            }
            void this.loadPage(1, this.query);
        }
        filterLocalOptions(query) {
            const normalizedQuery = Select.normalizeSearchText(query);
            this.resultOptions = this.localOptions.filter(function (option) {
                return Select.normalizeSearchText(option.value).includes(normalizedQuery)
                    || Select.normalizeSearchText(option.label).includes(normalizedQuery);
            });
            this.stateKey = this.emptyStateKey(query);
            this.more.hidden = true;
            this.render();
        }
        static normalizeSearchText(value) {
            return String(value || "")
                .trim()
                .toLocaleLowerCase()
                .normalize("NFD")
                .replace(/\p{M}+/gu, "");
        }
        async loadPage(page, query) {
            if (query.length < this.minLength) { return; }
            this.page = page;
            if (this.controller) { this.controller.abort(); }
            this.controller = new AbortController();
            const requestId = ++this.requestId;
            this.stateKey = "admin.select.loading";
            this.render();
            try {
                const url = new URL(this.source, window.location.origin);
                url.searchParams.set(this.searchParam, query);
                url.searchParams.set("page", String(this.page));
                const response = await HttpHelper.get(url.toString(), { signal: this.controller.signal });
                if (requestId !== this.requestId || query !== this.query) { return; }
                this.setOptions(response.items || [], query === "" ? "admin.select.no_options" : this.noResultsKey);
                this.loaded = true;
                this.more.hidden = !(response.pagination && response.pagination.hasMore);
            } catch (error) {
                if (error.name !== "AbortError") { this.stateKey = "admin.select.error"; this.more.hidden = true; this.render(); }
            }
        }
        nextPage() { void this.loadPage(this.page + 1, this.query); }
        setOptions(items, emptyStateKey) {
            const selected = this.selectedOptions();
            this.resultOptions = items;
            this.element.replaceChildren();
            items.forEach(function (item) {
                const option = new Option(item.label, item.value, false, selected.some(function (value) { return value.value === item.value; }));
                this.element.appendChild(option);
            }, this);
            selected.filter(function (value) { return !items.some(function (item) { return item.value === value.value; }); }).forEach(function (option) { this.element.appendChild(option); }, this);
            this.stateKey = items.length === 0 && !this.canCreateValue() ? emptyStateKey : "";
            this.render();
        }
        selectedOptions() { return Array.from(this.element.options).filter(function (option) { return option.selected; }); }

        emptyStateKey(query) {
            if (this.resultOptions.length !== 0 || this.canCreateValue()) {
                return "";
            }

            return query === "" ? "admin.select.no_options" : this.noResultsKey;
        }

        hasNormalizedOption(value) {
            const normalized = Select.normalizeSearchText(value);
            return Array.from(this.element.options).some(function (option) {
                return Select.normalizeSearchText(option.value) === normalized || Select.normalizeSearchText(option.text) === normalized;
            });
        }

        hasSelectedNormalizedValue(value, except) {
            const normalized = Select.normalizeSearchText(value);
            return this.selectedOptions().some(function (option) {
                return option !== except && Select.normalizeSearchText(option.value) === normalized;
            });
        }

        canCreateValue() {
            return this.allowCreate && this.query !== "" && !this.hasNormalizedOption(this.query);
        }

        createValue() {
            const value = this.query.trim();
            if (!this.canCreateValue() || this.hasSelectedNormalizedValue(value, null)) {
                return;
            }

            const option = new Option(value, value, false, true);
            this.element.appendChild(option);
            this.localOptions.push({ value: value, label: value });
            this.element.dispatchEvent(new Event("change", { bubbles: true }));
            this.searchInput.value = "";
            this.startSearch();
            this.render();
        }

        selectOption(option) {
            if (this.element.multiple) {
                if (!option.selected && this.hasSelectedNormalizedValue(option.value, option)) {
                    return;
                }
                if (!option.selected && this.allowCreate) {
                    this.element.appendChild(option);
                }
                option.selected = !option.selected;
            } else {
                Array.from(this.element.options).forEach(function (candidate) {
                    candidate.selected = candidate === option;
                });
                this.close();
            }
            this.element.dispatchEvent(new Event("change", { bubbles: true }));
            this.render();
        }
        render() {
            const selected = this.selectedOptions();
            this.trigger.replaceChildren();
            this.trigger.disabled = this.element.disabled;
            this.root.classList.toggle("is-disabled", this.element.disabled);
            this.root.classList.toggle("is-invalid", this.element.classList.contains("is-invalid"));
            if (selected.length === 0) {
                const placeholder = document.createElement("span");
                const key = this.element.getAttribute("data-lemonade-select-placeholder-key") || "admin.select.search";
                placeholder.textContent = I18n.t(key);
                placeholder.setAttribute("data-lemonade-i18n", key);
                this.trigger.appendChild(placeholder);
            } else if (!this.element.multiple) {
                const value = document.createElement("span");
                value.className = "lm-select-value";
                value.textContent = selected[0].text;
                this.trigger.appendChild(value);
            } else {
                selected.forEach(function (option) {
                    const token = document.createElement("span"); token.className = "lm-select-token";
                    const label = document.createElement("span"); label.className = "lm-select-token-label"; label.textContent = option.text; token.appendChild(label);
                    const remove = document.createElement("span"); remove.className = "lm-select-token-remove"; remove.textContent = "×"; token.appendChild(remove);
                    token.addEventListener("click", function (event) { event.preventDefault(); event.stopPropagation(); option.selected = false; this.element.dispatchEvent(new Event("change", { bubbles: true })); this.render(); }.bind(this));
                    this.trigger.appendChild(token);
                }, this);
            }
            const indicator = document.createElement("i");
            indicator.className = BootstrapIcons.className("chevron-down");
            indicator.classList.add("lm-select-indicator");
            indicator.setAttribute("aria-hidden", "true");
            this.trigger.appendChild(indicator);
            this.results.replaceChildren();
            if (this.stateKey) {
                const state = document.createElement("span"); state.className = "lm-select-state"; state.textContent = I18n.t(this.stateKey); state.setAttribute("data-lemonade-i18n", this.stateKey); this.results.appendChild(state);
            }
            this.resultOptions.forEach(function (result) {
                const option = Array.from(this.element.options).find(function (candidate) { return candidate.value === result.value; });
                if (!option) { return; }
                const item = document.createElement("button"); item.type = "button"; item.className = "lm-select-option" + (option.selected ? " is-selected" : ""); item.setAttribute("role", "option"); item.setAttribute("aria-selected", option.selected ? "true" : "false"); item.textContent = result.label;
                item.setAttribute("data-lemonade-select-option", "");
                item.addEventListener("click", function () { this.selectOption(option); }.bind(this));
                this.results.appendChild(item);
            }, this);
            if (this.canCreateValue()) {
                const create = document.createElement("button");
                create.type = "button";
                create.className = "lm-select-option lm-select-option-create";
                create.textContent = I18n.t(this.createLabelKey, { name: this.query });
                create.setAttribute("data-lemonade-i18n", this.createLabelKey);
                create.setAttribute("data-lemonade-i18n-param-name", this.query);
                create.addEventListener("click", this.createValue.bind(this));
                this.results.appendChild(create);
            }
            this.positionDropdown();
        }

        destroy() {
            if (this.destroyed) {
                return;
            }

            this.destroyed = true;
            if (this.controller) {
                this.controller.abort();
            }
            if (this.searchDebounce) {
                this.searchDebounce.cancel();
            }
            this.dropdown.remove();
            this.listenerRemovers.forEach(function (remove) {
                remove();
            });
            this.listenerRemovers = [];
            this.root.classList.remove("is-open");
            this.trigger.setAttribute("aria-expanded", "false");
        }
        static initAll(context) {
            const root = context || document;
            const elements = root.matches && root.matches("select.form-select") ? [root] : [];
            elements.push(...DomHelper.queryAll("select.form-select", root));
            elements.forEach(function (element) {
                if (!element.lemonadeSelect) {
                    element.lemonadeSelect = new Select(element);
                    element.lemonadeSelect.init();
                }
            });
        }
        static destroyAll(context) {
            const root = context || document;
            const elements = root.matches && root.matches("select.form-select") ? [root] : [];
            elements.push(...DomHelper.queryAll("select.form-select", root));
            elements.forEach(function (element) {
                if (element.lemonadeSelect) {
                    element.lemonadeSelect.destroy();
                    delete element.lemonadeSelect;
                }
            });
        }

        static mount(root = document) {
            this.initAll(root);
        }

        static destroy(root = document) {
            this.destroyAll(root);
        }
    };

export function mount(root = document) {
    Select.mount(root);
}

export function destroy(root = document) {
    Select.destroy(root);
}
