/*!
 * Lemonade DataGrid
 *
 * Shared DataGrid component for Lemonade Admin.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { Dropdown } from "./lemonade-dropdown.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { StorageHelper } from "../core/lemonade-storage-helper.js";
    export class DataGrid {
        constructor(element) {
            this.element = element;
            this.key = element.getAttribute("data-lemonade-grid-key");
            this.source = element.getAttribute("data-lemonade-source");
            this.i18nSource = element.getAttribute("data-lemonade-i18n-source");
            this.gridTranslations = {};
            this.i18nRequestId = 0;
            this.storageKey = `lemonade.datagrid.${this.key}`;
            this.searchParam = element.getAttribute("data-lemonade-search-param") || "q";
            this.pageParam = element.getAttribute("data-lemonade-page-param") || "page";
            this.pageSizeParam = element.getAttribute("data-lemonade-page-size-param") || "pageSize";
            this.sortParam = element.getAttribute("data-lemonade-sort-param") || "sort";
            this.directionParam = element.getAttribute("data-lemonade-direction-param") || "direction";
            this.controller = null;
            this.requestId = 0;
            this.searchDebounce = null;
            this.isSearchPending = false;
            this.hasLoadedData = false;
            this.currentStatus = "ready";
            this.selectedIds = new Set();
            this.pagination = null;
            this.preferences = StorageHelper.get(this.storageKey) || {};
            this.filterOptions = {};
            this.controls = {
                search: element.querySelector("[data-lemonade-grid-search]"),
                filters: DomHelper.queryAll("[data-lemonade-filter]", element),
                filterTriggers: DomHelper.queryAll("[data-lemonade-grid-filter-trigger], .lm-filter-dropdown > [data-lemonade-dropdown-trigger]", element),
                views: DomHelper.queryAll("[data-lemonade-grid-view]", element),
                filterApply: element.querySelector("[data-lemonade-grid-filter-apply]"),
                reload: element.querySelector("[data-lemonade-grid-reload]"),
                reset: element.querySelector("[data-lemonade-grid-reset]"),
                chips: element.querySelector("[data-lemonade-grid-filter-chips]"),
                pageSize: element.querySelector("[data-lemonade-page-size]"),
                selectAll: DomHelper.queryAll("[data-lemonade-grid-select-all]", element),
                bulkActions: DomHelper.queryAll("[data-lemonade-grid-bulk-action]", element),
                selectionActions: element.querySelector(".lm-datagrid-selection-actions"),
                selectionBar: element.querySelector("[data-lemonade-grid-selection-bar]"),
                selectionCount: element.querySelector("[data-lemonade-grid-selection-count]"),
                clearSelection: DomHelper.queryAll("[data-lemonade-grid-clear-selection]", element),
                summary: element.querySelector("[data-lemonade-grid-summary]"),
                pagination: element.querySelector("[data-lemonade-grid-pagination]"),
                loading: element.querySelector("[data-lemonade-grid-loading]"),
                empty: element.querySelector("[data-lemonade-grid-empty]"),
                error: element.querySelector("[data-lemonade-grid-error]"),
                retry: element.querySelector("[data-lemonade-grid-retry]")
            };
            this.dataArea = this.getDataArea();
            this.ensureStateElements();
            this.ensureBusyOverlay();
            this.viewQueryNames = this.getViewQueryNames();
            this.defaultState = this.getDefaultState();
            this.state = this.createState();
            this.viewsToolbar = element.querySelector(".lm-datagrid-views");
            this.primaryViews = this.viewsToolbar ? this.viewsToolbar.querySelector(".lm-datagrid-views-list") : null;
            this.viewsScroll = null;
            this.viewItems = this.controls.views.map(function (view) {
                return { view: view, item: view.closest(".lm-datagrid-view-item") || view };
            });
            this.viewsOverflowFrame = null;
            this.viewsResizeObserver = null;
            this.listenerRemovers = [];
            this.destroyed = false;
        }

        async init() {
            if (!this.key) {
                return;
            }

            this.prepareSelectionControls();
            this.prepareFilterTriggers();
            this.prepareGridTranslationFallbacks();
            this.setupViewsOverflow();
            this.bindEvents();
            await this.loadGridTranslations();
            if (this.destroyed) {
                return;
            }
            this.renderState();
            void this.refresh({ preserveSelection: true });
        }

        destroy() {
            this.destroyed = true;
            this.controller?.abort();
            this.viewsResizeObserver?.disconnect();
            if (this.viewsOverflowFrame) {
                window.cancelAnimationFrame(this.viewsOverflowFrame);
                this.viewsOverflowFrame = null;
            }
            this.listenerRemovers.forEach(function (remove) { remove(); });
            this.listenerRemovers = [];
            delete this.element.lemonadeDataGrid;
        }

        getDataArea() {
            return this.element.querySelector(".data-grid") || this.element.querySelector(".table-responsive");
        }

        ensureStateElements() {
            const container = this.dataArea && this.dataArea.classList.contains("data-grid") ? this.dataArea : this.element;
            ["loading", "empty", "error"].forEach(function (state) {
                const property = state === "loading" ? "loading" : state;
                if (this.controls[property]) {
                    return;
                }
                const element = document.createElement("div");
                element.className = "lm-grid-state";
                element.setAttribute(`data-lemonade-grid-${state}`, "");
                element.hidden = true;
                container.appendChild(element);
                this.controls[property] = element;
            }, this);
        }

        ensureBusyOverlay() {
            if (!this.dataArea) {
                return;
            }
            let overlay = this.dataArea.querySelector("[data-lemonade-grid-busy-overlay]");
            if (!overlay) {
                overlay = document.createElement("div");
                overlay.className = "lm-grid-busy-overlay";
                overlay.setAttribute("data-lemonade-grid-busy-overlay", "");
                overlay.setAttribute("aria-hidden", "true");
                this.dataArea.appendChild(overlay);
            }
            const spinner = document.createElement("span");
            spinner.className = "spinner-border spinner-border-sm";
            spinner.setAttribute("aria-hidden", "true");
            const label = document.createElement("span");
            label.className = "lm-grid-busy-overlay-label";
            overlay.replaceChildren(spinner, label);
            overlay.hidden = true;
            this.controls.busyOverlay = overlay;
        }

        prepareGridTranslationFallbacks() {
            DomHelper.queryAll("[data-lemonade-grid-i18n]", this.element).forEach(function (element) {
                if (!element.hasAttribute("data-lemonade-grid-i18n-fallback")) {
                    element.setAttribute("data-lemonade-grid-i18n-fallback", element.textContent);
                }
            });
        }

        prepareFilterTriggers() {
            this.controls.filterTriggers.forEach(function (trigger) {
                trigger.classList.add("lm-datagrid-toolbar-icon");
                trigger.setAttribute("data-lemonade-grid-filter-trigger", "");
                trigger.setAttribute("data-lemonade-i18n-aria-label", "datagrid.filters");
                trigger.setAttribute("data-lemonade-i18n-title", "datagrid.filters");
                let icon = trigger.querySelector(".bi");
                if (!icon) {
                    icon = document.createElement("i");
                    icon.setAttribute("aria-hidden", "true");
                }
                BootstrapIcons.set(icon, "funnel");
                const count = document.createElement("span");
                count.className = "lm-datagrid-filter-count";
                count.setAttribute("data-lemonade-grid-filter-count", "");
                count.setAttribute("aria-hidden", "true");
                count.hidden = true;
                trigger.replaceChildren(icon, count);
            });
        }

        async loadGridTranslations() {
            if (!this.i18nSource || !I18n) {
                return;
            }

            const locale = I18n.getLocale();
            const requestId = ++this.i18nRequestId;
            const cacheKey = `${this.key}:${locale}`;
            const cache = DataGrid.i18nCache;

            if (Object.prototype.hasOwnProperty.call(cache, cacheKey)) {
                if (requestId !== this.i18nRequestId) {
                    return;
                }
                this.gridTranslations = cache[cacheKey];
                this.applyGridTranslations();
                return;
            }

            try {
                const url = new URL(this.i18nSource, window.location.origin);
                url.searchParams.set("locale", locale);
                const translations = await HttpHelper.get(url.toString());
                if (!translations || typeof translations !== "object" || Array.isArray(translations)) {
                    throw new Error("Invalid Lemonade DataGrid translation response.");
                }
                cache[cacheKey] = translations;
                if (requestId !== this.i18nRequestId) {
                    return;
                }
                this.gridTranslations = translations;
            } catch (error) {
                if (requestId !== this.i18nRequestId) {
                    return;
                }
                this.gridTranslations = {};
            }

            this.applyGridTranslations();
            this.scheduleViewsOverflow();
        }

        setupViewsOverflow() {
            if (!this.viewsToolbar || !this.primaryViews || !this.viewItems.length) {
                return;
            }

            const scroll = document.createElement("div");
            scroll.className = "lm-datagrid-views-scroll";
            this.primaryViews.insertAdjacentElement("beforebegin", scroll);
            scroll.appendChild(this.primaryViews);
            this.viewsScroll = scroll;

            const more = document.createElement("div");
            more.className = "lm-datagrid-views-more";
            more.setAttribute("data-lemonade-dropdown", "");
            more.hidden = true;

            const toggle = document.createElement("button");
            toggle.className = "lm-datagrid-views-more-toggle";
            toggle.type = "button";
            toggle.setAttribute("data-lemonade-dropdown-trigger", "");
            toggle.setAttribute("aria-expanded", "false");

            const label = document.createElement("span");
            label.setAttribute("data-lemonade-grid-views-more-label", "");
            toggle.appendChild(label);

            const icon = document.createElement("i");
            BootstrapIcons.set(icon, "chevron-down");
            icon.setAttribute("aria-hidden", "true");
            toggle.appendChild(icon);

            const menu = document.createElement("div");
            menu.className = "lm-dropdown-menu lm-datagrid-views-more-menu";
            menu.setAttribute("data-lemonade-dropdown-panel", "");
            menu.hidden = true;
            more.append(toggle, menu);
            this.viewsScroll.insertAdjacentElement("afterend", more);

            this.controls.viewsMore = more;
            this.controls.viewsMoreLabel = label;
            this.controls.viewsMoreMenu = menu;
            this.setViewsMoreLabel(null);

            Dropdown.create(more, { placement: "bottom-end" });

            if ("ResizeObserver" in window) {
                this.viewsResizeObserver = new ResizeObserver(this.scheduleViewsOverflow.bind(this));
                this.viewsResizeObserver.observe(this.viewsToolbar);
            }

            this.scheduleViewsOverflow();
        }

        scheduleViewsOverflow() {
            if (!this.viewsToolbar || !this.primaryViews || !this.viewsScroll || this.viewsOverflowFrame) {
                return;
            }

            this.viewsOverflowFrame = window.requestAnimationFrame(function () {
                this.viewsOverflowFrame = null;
                this.updateViewsOverflow();
            }.bind(this));
        }

        updateViewsOverflow() {
            if (!this.controls.viewsMore || !this.controls.viewsMoreMenu) {
                return;
            }

            this.viewItems.forEach(function (record) {
                this.moveViewItem(record, false);
            }, this);
            this.controls.viewsMore.hidden = true;

            if (this.primaryViews.scrollWidth <= this.viewsToolbar.clientWidth) {
                this.renderViewsMore();
                return;
            }

            this.controls.viewsMore.hidden = false;
            this.controls.viewsMore.style.visibility = "hidden";

            let primaryItems = this.getPrimaryViewItems();
            while (primaryItems.length > 1 && this.primaryViews.scrollWidth > this.viewsScroll.clientWidth) {
                const activeItem = primaryItems.find(function (record) {
                    return record.view.getAttribute("data-lemonade-grid-view") === this.state.activeView;
                }, this);
                const itemToMove = primaryItems.slice().reverse().find(function (record) {
                    return record !== activeItem;
                }) || primaryItems[primaryItems.length - 1];
                this.moveViewItem(itemToMove, true);
                primaryItems = this.getPrimaryViewItems();
            }

            this.controls.viewsMore.style.visibility = "";
            if (!this.controls.viewsMoreMenu.children.length) {
                this.controls.viewsMore.hidden = true;
            }
            this.renderViewsMore();
        }

        getPrimaryViewItems() {
            return this.viewItems.filter(function (record) {
                return record.item.parentNode === this.primaryViews;
            }, this);
        }

        moveViewItem(record, isOverflow) {
            const view = record.view;
            view.classList.toggle("lm-datagrid-view", !isOverflow);
            view.classList.toggle("lm-dropdown-item", isOverflow);

            if (isOverflow) {
                this.controls.viewsMoreMenu.prepend(record.item);
                let check = view.querySelector(".lm-datagrid-views-more-check");
                if (!check) {
                    check = document.createElement("i");
                    check.className = BootstrapIcons.className("check2") + " lm-datagrid-views-more-check";
                    check.setAttribute("aria-hidden", "true");
                    view.appendChild(check);
                }
                return;
            }

            this.primaryViews.appendChild(record.item);
            const check = view.querySelector(".lm-datagrid-views-more-check");
            if (check) {
                check.remove();
            }
        }

        getGridTranslation(key) {
            return key.split(".").reduce(function (value, part) {
                return value && Object.prototype.hasOwnProperty.call(value, part) ? value[part] : null;
            }, this.gridTranslations);
        }

        applyGridTranslations() {
            DomHelper.queryAll("[data-lemonade-grid-i18n]", this.element).forEach(function (element) {
                const translation = this.getGridTranslation(element.getAttribute("data-lemonade-grid-i18n"));
                element.textContent = typeof translation === "string" ? translation : element.getAttribute("data-lemonade-grid-i18n-fallback");
            }, this);
            this.scheduleViewsOverflow();
        }

        createState() {
            if (Object.keys(this.preferences).length) {
                return this.getStoredState();
            }
            return this.cloneState(this.defaultState);
        }

        getStoredState() {
            const state = this.cloneState(this.defaultState);
            this.controls.filters.forEach(function (control) {
                const name = control.getAttribute("data-lemonade-filter");
                if (Object.prototype.hasOwnProperty.call(this.preferences.filters || {}, name)) {
                    state.filters[name] = this.preferences.filters[name];
                }
            }, this);
            if (Object.prototype.hasOwnProperty.call(this.preferences, "sort")) {
                const storedSort = String(this.preferences.sort || "");
                if (storedSort.startsWith("-")) {
                    state.sort = storedSort.slice(1);
                    state.direction = "desc";
                } else if (storedSort) {
                    state.sort = storedSort;
                    state.direction = this.preferences.direction === "desc" ? "desc" : "asc";
                }
            }
            if (Object.prototype.hasOwnProperty.call(this.preferences, "pageSize")) {
                state.pageSize = Number(this.preferences.pageSize) || state.pageSize;
            }
            if (Object.prototype.hasOwnProperty.call(this.preferences, "page")) {
                state.page = Number(this.preferences.page) || state.page;
            }
            if (this.controls.search && Object.prototype.hasOwnProperty.call(this.preferences, "search")) {
                state.search = String(this.preferences.search || "");
            }
            const storedView = this.controls.views.find(function (view) {
                return view.getAttribute("data-lemonade-grid-view") === this.preferences.activeView;
            }, this);
            if (storedView) {
                this.getStateForView(storedView, state);
            }
            state.filters = this.normalizeFilters(state.filters);
            state.activeView = storedView ? state.activeView : this.getViewForState(state);
            return state;
        }

        getDefaultState() {
            const defaultView = this.controls.views.find(function (view) {
                return view.getAttribute("data-lemonade-grid-view") === this.element.getAttribute("data-lemonade-default-view");
            }, this) || this.controls.views[0];
            const state = {
                filters: this.getDefaultFilters(),
                search: this.controls.search ? this.controls.search.value : "",
                sort: this.element.getAttribute("data-lemonade-default-sort") || "",
                direction: this.element.getAttribute("data-lemonade-default-direction") || "asc",
                page: 1,
                pageSize: Number(this.element.getAttribute("data-lemonade-default-page-size") || (this.controls.pageSize && this.controls.pageSize.value) || 1),
                viewQuery: {},
                activeView: defaultView ? defaultView.getAttribute("data-lemonade-grid-view") || "" : ""
            };
            return defaultView ? this.getStateForView(defaultView, state) : state;
        }

        getDefaultFilters() {
            const filters = {};
            this.controls.filters.forEach(function (control) {
                filters[control.getAttribute("data-lemonade-filter")] = control.value;
            });
            return this.normalizeFilters(filters);
        }

        getFilterEmptyValue(control) {
            return control.hasAttribute("data-lemonade-filter-empty-value") ? control.getAttribute("data-lemonade-filter-empty-value") : "";
        }

        isEmptyFilterValue(control, value) {
            return value === null || value === undefined || value === "" || String(value) === this.getFilterEmptyValue(control);
        }

        normalizeFilters(filters) {
            const normalized = {};
            const values = filters || {};
            this.controls.filters.forEach(function (control) {
                const name = control.getAttribute("data-lemonade-filter");
                const value = values[name];
                if (!this.isEmptyFilterValue(control, value)) {
                    normalized[name] = value;
                }
            }, this);
            return normalized;
        }

        hasActiveFilters() {
            this.state.filters = this.normalizeFilters(this.state.filters);
            return Object.keys(this.state.filters).length > 0;
        }

        getPersistentPreferences(state) {
            const source = state || this.state;
            const preferences = {
                activeView: source.activeView || "",
                filters: this.normalizeFilters(source.filters),
                sort: source.sort || "",
                direction: source.direction === "desc" ? "desc" : "asc",
                page: Number(source.page) || 1,
                pageSize: Number(source.pageSize) || 0
            };
            if (this.controls.search) {
                preferences.search = String(source.search || "");
            }
            return preferences;
        }

        areFiltersEqual(first, second) {
            const firstFilters = first || {};
            const secondFilters = second || {};
            const names = Array.from(new Set(Object.keys(firstFilters).concat(Object.keys(secondFilters))));
            return names.every(function (name) {
                const firstValue = Object.prototype.hasOwnProperty.call(firstFilters, name) ? firstFilters[name] : "";
                const secondValue = Object.prototype.hasOwnProperty.call(secondFilters, name) ? secondFilters[name] : "";
                return String(firstValue) === String(secondValue);
            });
        }

        hasModifiedPreferences() {
            const current = this.getPersistentPreferences(this.state);
            const defaults = this.getPersistentPreferences(this.defaultState);
            return current.activeView !== defaults.activeView
                || current.search !== defaults.search
                || current.sort !== defaults.sort
                || current.direction !== defaults.direction
                || current.page !== defaults.page
                || current.pageSize !== defaults.pageSize
                || !this.areFiltersEqual(current.filters, defaults.filters);
        }

        getFilterControlValue(control) {
            const name = control.getAttribute("data-lemonade-filter");
            return Object.prototype.hasOwnProperty.call(this.state.filters, name) ? this.state.filters[name] : this.getFilterEmptyValue(control);
        }

        applyFilterControls() {
            const filters = {};
            this.controls.filters.forEach(function (control) {
                filters[control.getAttribute("data-lemonade-filter")] = control.value;
            });
            this.update({ filters: this.normalizeFilters(filters), page: 1 });
        }

        syncFilterControls() {
            this.controls.filters.forEach(function (control) {
                control.value = this.getFilterControlValue(control);
                if (control.lemonadeSelect) {
                    control.lemonadeSelect.render();
                }
            }, this);
        }

        closeFilterPanel(trigger) {
            trigger.closest("[data-lemonade-dropdown]")?.lemonadeDropdown?.close("apply");
        }

        cloneState(state) {
            return {
                filters: Object.assign({}, state.filters),
                search: state.search,
                sort: state.sort,
                direction: state.direction,
                page: state.page,
                pageSize: state.pageSize,
                viewQuery: Object.assign({}, state.viewQuery),
                activeView: state.activeView
            };
        }

        bindEvents() {
            const self = this;

            if (this.controls.search) {
                this.syncSearchControl();
                this.searchDebounce = EventHelper.debounce(function () {
                    self.applyPendingSearch();
                }, 500);
                EventHelper.on(this.controls.search, "input", function () {
                    self.handleSearchInput();
                });
                EventHelper.on(this.controls.search, "search", function () {
                    if (!self.controls.search.value) {
                        self.clearSearch();
                    }
                });
                EventHelper.on(this.controls.search, "keydown", function (event) {
                    if (event.key === "Enter") {
                        event.preventDefault();
                        self.flushPendingSearch();
                    }
                });
            }

            this.syncFilterControls();

            this.controls.filterTriggers.forEach(function (trigger) {
                Dropdown.create(trigger.closest("[data-lemonade-dropdown]"), {
                    placement: "bottom-end",
                    closeOnInsideClick: false,
                    relatedRoots: function () {
                        return self.controls.filters.map(function (control) {
                            return control.lemonadeSelect?.dropdown;
                        }).filter(Boolean);
                    },
                    onOpen: function () { self.syncFilterControls(); },
                    onClose: function () { self.syncFilterControls(); },
                });
            });

            EventHelper.on(this.controls.filterApply, "click", function () {
                self.applyFilterControls();
                self.controls.filterTriggers.forEach(function (trigger) {
                    self.closeFilterPanel(trigger);
                });
            });
            EventHelper.on(this.controls.pageSize, "change", function () { self.update({ pageSize: Number(self.controls.pageSize.value), page: 1 }); });
            this.controls.selectAll.forEach(function (control) {
                EventHelper.on(control, "change", function () { self.toggleSelectAll(control.checked); });
            });
            this.controls.clearSelection.forEach(function (control) {
                EventHelper.on(control, "click", function () { self.resetSelection(); });
            });
            EventHelper.on(this.element, "change", function (event) { self.handleGridChange(event); });
            EventHelper.on(this.element, "click", function (event) { self.handleGridClick(event); });
            EventHelper.on(this.element, "lemonade:action:success", function (event) {
                if (event.detail && event.detail.response && event.detail.response.refreshPage === true) {
                    window.location.assign(window.location.href);
                    return;
                }
                if (DomHelper.closest(event.target, "[data-lemonade-grid-bulk-action]")) {
                    self.resetSelection();
                    void self.refresh({ preserveSelection: true });
                    return;
                }
                if (DomHelper.closest(event.target, "[data-lemonade-grid-refresh]")) {
                    void self.refresh();
                }
            });
            EventHelper.on(this.element, "lemonade:grid:refresh", function () {
                void self.refresh({ preserveSelection: true });
            });
            EventHelper.on(document, "lemonade:locale:changed", function () {
                void self.loadGridTranslations().then(function () {
                    self.renderState();
                    return self.refresh({ preserveSelection: true });
                }).catch(function (error) {
                    console.error("Lemonade DataGrid failed to refresh after a locale change.", error);
                });
            });
        }

        handleGridClick(event) {
            const bulkDownload = DomHelper.closest(event.target, "[data-lemonade-grid-bulk-download]");
            const removeFilter = DomHelper.closest(event.target, "[data-lemonade-grid-filter-remove]");
            const clearFilters = DomHelper.closest(event.target, "[data-lemonade-grid-clear-filters]");
            const reload = DomHelper.closest(event.target, "[data-lemonade-grid-reload]");
            const reset = DomHelper.closest(event.target, "[data-lemonade-grid-reset]");
            const retry = DomHelper.closest(event.target, "[data-lemonade-grid-retry]");
            const viewElement = DomHelper.closest(event.target, "[data-lemonade-grid-view]");
            const sortElement = DomHelper.closest(event.target, "[data-lemonade-sort]");
            const pageElement = DomHelper.closest(event.target, "[data-lemonade-grid-page]");

            if (bulkDownload) {
                event.preventDefault();
                this.submitBulkDownload(bulkDownload);
                return;
            }
            if (removeFilter) {
                event.preventDefault();
                this.removeFilter(removeFilter.getAttribute("data-lemonade-grid-filter-remove"));
                return;
            }
            if (clearFilters) {
                event.preventDefault();
                this.clearFilters();
                return;
            }
            if (reload) {
                event.preventDefault();
                void this.refresh();
                return;
            }
            if (reset) {
                event.preventDefault();
                this.resetView();
                return;
            }
            if (retry) {
                event.preventDefault();
                void this.refresh({ preserveSelection: true });
                return;
            }
            if (viewElement) {
                event.preventDefault();
                this.applyView(viewElement);
                return;
            }
            if (sortElement) {
                event.preventDefault();
                this.toggleSort(sortElement.getAttribute("data-lemonade-sort"));
            }
            if (pageElement) {
                event.preventDefault();
                this.update({ page: Number(pageElement.getAttribute("data-lemonade-grid-page")) || 1 });
            }
        }

        submitBulkDownload(action) {
            if (action.disabled || this.selectedIds.size === 0) {
                return;
            }

            const token = document.querySelector('meta[name="csrf-token"]');
            const form = document.createElement("form");
            form.method = "POST";
            form.action = action.getAttribute("data-lemonade-url") || "";
            form.hidden = true;

            if (token && token.content) {
                const csrf = document.createElement("input");
                csrf.type = "hidden";
                csrf.name = "LEMONADE_CSRF";
                csrf.value = token.content;
                form.appendChild(csrf);
            }

            this.selectedIds.forEach(function (id) {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "ids[]";
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            form.remove();
        }

        handleSelection(event) {
            const control = event.target;
            if (!control.matches("[data-lemonade-grid-select]")) {
                return;
            }
            if (control.checked) {
                this.selectedIds.add(control.value);
            } else {
                this.selectedIds.delete(control.value);
            }
            this.updateBulkControls();
        }

        handleGridChange(event) {
            const filterControl = DomHelper.closest(event.target, "[data-lemonade-filter]");
            if (filterControl) {
                return;
            }
            this.handleSelection(event);
        }

        toggleSelectAll(isSelected) {
            this.getSelectionControls().forEach(function (control) {
                control.checked = isSelected;
                if (isSelected) { this.selectedIds.add(control.value); } else { this.selectedIds.delete(control.value); }
            }, this);
            this.controls.selectAll.forEach(function (control) { control.checked = isSelected; control.indeterminate = false; });
            this.updateBulkControls();
        }

        toggleSort(column) {
            const direction = this.state.sort === column && this.state.direction === "asc" ? "desc" : "asc";
            this.update({ sort: column, direction: direction, page: 1 });
        }

        clearFilters() {
            this.update({ filters: {}, page: 1 });
        }

        resetView() {
            this.cancelPendingSearch();
            this.preferences = {};
            this.state = this.cloneState(this.defaultState);
            this.resetSelection();
            this.persistPreferences();
            this.renderState();
            void this.refresh({ preserveSelection: true });
        }

        removeFilter(name) {
            if (!name) { return; }
            const filters = Object.assign({}, this.state.filters);
            delete filters[name];
            this.controls.filters.forEach(function (filter) {
                if (filter.getAttribute("data-lemonade-filter") === name) { filter.value = this.getFilterEmptyValue(filter); }
            }, this);
            this.update({ filters: filters, page: 1 });
        }

        update(changes) {
            this.state = Object.assign({}, this.state, changes);
            this.state.filters = this.normalizeFilters(this.state.filters);
            this.state.activeView = changes.activeView || this.getViewForState(this.state);
            this.resetSelection();
            this.persistPreferences();
            this.renderState();
            void this.refresh({ preserveSelection: true });
        }

        handleSearchInput() {
            if (!this.controls.search || !this.searchDebounce) {
                return;
            }
            this.isSearchPending = true;
            this.abortActiveRequest({ clearBusy: true });
            this.searchDebounce();
        }

        applyPendingSearch() {
            if (!this.controls.search) {
                return;
            }
            this.isSearchPending = false;
            this.applySearch(this.controls.search.value);
        }

        applySearch(value) {
            const search = String(value || "");
            if (this.state.search === search) {
                if (this.element.classList.contains("is-loading")) {
                    void this.refresh({ preserveSelection: true });
                }
                return;
            }
            this.update({ search: search, page: 1 });
        }

        syncSearchControl() {
            if (!this.controls.search || this.isSearchPending) {
                return;
            }
            const search = String(this.state.search || "");
            if (this.controls.search.value !== search) {
                this.controls.search.value = search;
            }
        }

        cancelPendingSearch() {
            if (this.searchDebounce) {
                this.searchDebounce.cancel();
            }
            this.isSearchPending = false;
        }

        flushPendingSearch() {
            if (!this.controls.search || !this.isSearchPending) {
                return;
            }
            this.searchDebounce.cancel();
            this.applyPendingSearch();
        }

        clearSearch() {
            this.cancelPendingSearch();
            this.applySearch("");
        }

        synchronizePendingSearch() {
            if (!this.controls.search || !this.isSearchPending) {
                return;
            }
            this.searchDebounce.cancel();
            this.isSearchPending = false;
            this.state.search = this.controls.search.value;
        }

        setUrlValue(url, name, value) {
            if (value === "" || value === null || value === undefined) { url.searchParams.delete(name); return; }
            url.searchParams.set(name, value);
        }

        persistPreferences() {
            this.state.filters = this.normalizeFilters(this.state.filters);
            const preferences = this.getPersistentPreferences(this.state);
            if (!preferences.search) { delete preferences.search; }
            StorageHelper.set(this.storageKey, preferences);
        }

        async refresh(options) {
            const settings = options || {};
            if (!settings.preserveSelection) { this.resetSelection(); }
            if (!this.source) { this.setStatus("ready"); return; }
            this.abortActiveRequest();
            this.controller = new AbortController();
            const requestId = ++this.requestId;
            this.setStatus("loading");
            try {
                const url = new URL(this.source, window.location.origin);
                this.appendRequestState(url);
                const response = await HttpHelper.get(url.toString(), { signal: this.controller.signal });
                if (requestId !== this.requestId) {
                    return;
                }
                this.applyResponse(response);
                this.hasLoadedData = true;
                this.setStatus(response.pagination.total > 0 ? "ready" : "empty");
                this.element.dispatchEvent(new CustomEvent("lemonade:grid:loaded", { bubbles: true, detail: { response: response, state: this.state } }));
            } catch (error) {
                if (error.name !== "AbortError" && requestId === this.requestId) {
                    this.setStatus("error");
                    this.element.dispatchEvent(new CustomEvent("lemonade:grid:error", { bubbles: true, detail: { error: error } }));
                }
            } finally {
                if (requestId === this.requestId) {
                    this.controller = null;
                }
            }
        }

        abortActiveRequest(options) {
            if (!this.controller) {
                return;
            }
            this.controller.abort();
            this.controller = null;
            this.requestId += 1;
            if (options && options.clearBusy) {
                this.setStatus(this.pagination && Number(this.pagination.total) === 0 ? "empty" : "ready");
            }
        }

        appendRequestState(url) {
            this.getManagedQueryNames().forEach(function (name) { url.searchParams.delete(name); });
            if (this.controls.search) { this.setUrlValue(url, this.searchParam, this.state.search); }
            this.setUrlValue(url, this.sortParam, this.state.sort);
            this.setUrlValue(url, this.directionParam, this.state.direction);
            this.setUrlValue(url, this.pageParam, this.state.page);
            this.setUrlValue(url, this.pageSizeParam, this.state.pageSize);
            Object.keys(this.state.filters).forEach(function (name) { this.setUrlValue(url, name, this.state.filters[name]); }, this);
            Object.keys(this.state.viewQuery).forEach(function (name) { this.setUrlValue(url, name, this.state.viewQuery[name]); }, this);
        }

        applyResponse(response) {
            const body = this.element.querySelector("[data-lemonade-grid-body]");
            if (!response || !Array.isArray(response.columns) || !Array.isArray(response.filters) || !Array.isArray(response.items) || !response.sort || !response.pagination) {
                throw new Error("Invalid Lemonade DataGrid response.");
            }
            if (!body) { return; }
            this.renderRows(body, response.columns, response.items);
            this.pagination = response.pagination;
            this.state.page = Number(response.pagination.page) || this.state.page;
            this.state.pageSize = Number(response.pagination.perPage) || this.state.pageSize;
            this.state.sort = response.sort.column;
            this.state.direction = response.sort.direction;
            const filters = {};
            response.filters.forEach(function (filter) {
                this.filterOptions[filter.key] = Array.isArray(filter.options) ? filter.options : [];
                if (filter.value !== null && filter.value !== undefined) {
                    filters[filter.key] = filter.value;
                }
            }, this);
            this.state.filters = this.normalizeFilters(filters);
            this.prepareSelectionControls();
            this.persistPreferences();
            this.renderState();
        }

        renderRows(body, columns, items) {
            const fragment = document.createDocumentFragment();
            items.forEach(function (item) {
                const row = document.createElement("tr");
                row.setAttribute("data-lemonade-row-id", String(item.id));
                if (this.controls.selectAll.length) {
                    const selection = document.createElement("td");
                    selection.className = "check-col";
                    const control = document.createElement("input");
                    control.className = "form-check-input";
                    control.type = "checkbox";
                    control.value = String(item.id);
                    control.setAttribute("aria-label", I18n.t("admin.datagrid.selectItem"));
                    control.setAttribute("data-lemonade-grid-select", "");
                    selection.appendChild(control);
                    row.appendChild(selection);
                }
                columns.forEach(function (column) {
                    const cell = document.createElement("td");
                    if (column.class) { cell.className = column.class; }
                    if (column.key === "actions") {
                        cell.classList.add("lm-datagrid-actions-column");
                        this.renderActions(cell, item.actions || []);
                    } else {
                        this.renderCell(cell, item.cells[column.key]);
                    }
                    row.appendChild(cell);
                }, this);
                fragment.appendChild(row);
            }, this);
            body.replaceChildren(fragment);
        }

        renderCell(cell, value) {
            if (!Array.isArray(value)) {
                cell.textContent = value === null || value === undefined ? "" : String(value);
                return;
            }
            value.forEach(function (part) {
                if (!part || typeof part !== "object") { return; }
                if (part.type === "thumbnail") {
                    const profile = document.createElement("div");
                    profile.className = "lm-datagrid-profile";
                    const thumbnail = this.renderThumbnail(part.thumbnail);
                    const copy = document.createElement("div");
                    copy.className = "lm-datagrid-profile-copy";
                    const primary = part.url ? document.createElement("a") : document.createElement("strong");
                    primary.textContent = part.value || "";
                    if (part.url) {
                        primary.href = part.url;
                        if (part.download === true) { primary.download = ""; }
                    }
                    const secondary = document.createElement("small");
                    secondary.textContent = part.secondary || "";
                    copy.append(primary, secondary);
                    profile.append(thumbnail, copy);
                    cell.appendChild(profile);
                    return;
                }
                if (part.type === "datetime") {
                    const time = document.createElement("time");
                    time.dateTime = part.value || "";
                    time.setAttribute("data-lemonade-i18n-datetime", "");
                    time.textContent = part.value || "";
                    cell.appendChild(time);
                    return;
                }
                if (part.type === "stacked") {
                    const stacked = document.createElement("div");
                    stacked.className = "lm-datagrid-stacked";
                    const primary = document.createElement("div");
                    primary.textContent = part.value || "";
                    const secondary = document.createElement("small");
                    secondary.textContent = part.secondary || "";
                    stacked.append(primary, secondary);
                    cell.appendChild(stacked);
                    return;
                }
                const element = part.type === "link" ? document.createElement("a") : (part.type === "code" ? document.createElement("code") : document.createElement("span"));
                if (part.type === "link") {
                    element.href = part.url || "#";
                    if (part.modalUrl) {
                        element.setAttribute("data-lemonade-modal-form-url", part.modalUrl);
                        if (part.modalSize) {
                            element.setAttribute("data-lemonade-modal-size", part.modalSize);
                        }
                    }
                }
                if (part.flag) {
                    const flag = document.createElement("span");
                    flag.className = "admin-country-flag me-2";
                    flag.setAttribute("aria-hidden", "true");
                    flag.textContent = part.flag;
                    cell.appendChild(flag);
                }
                if (part.type === "status") { element.className = `badge ${part.class || ""}`; element.textContent = `● ${part.value || ""}`; }
                else { element.textContent = part.value || ""; }
                if (part.translationKey) { element.setAttribute("data-lemonade-i18n", part.translationKey); }
                cell.appendChild(element);
            }, this);
            if (I18n) { I18n.apply(cell); }
        }

        renderActions(cell, actions) {
            if (!actions.length) { return; }
            const actionGroup = document.createElement("div");
            actionGroup.className = "lm-row-action-group";
            const inlineActions = actions.filter(function (action) {
                return action.placement === "inline";
            });
            const primaryActions = actions.filter(function (action) {
                return action.placement === "primary";
            });
            const menuActions = actions.filter(function (action) {
                return action.placement !== "primary" && action.placement !== "inline";
            });
            if (inlineActions.length) {
                const inline = document.createElement("div");
                inline.className = "lm-row-inline-actions";
                inlineActions.forEach(function (action) {
                    inline.appendChild(this.createInlineActionControl(action));
                }, this);
                actionGroup.appendChild(inline);
            }
            primaryActions.forEach(function (action) {
                actionGroup.appendChild(this.createPrimaryActionControl(action));
            }, this);
            if (!menuActions.length) {
                cell.appendChild(actionGroup);
                return;
            }
            const dropdown = document.createElement("div");
            dropdown.className = "lm-row-actions";
            dropdown.setAttribute("data-lemonade-dropdown", "");
            const toggle = document.createElement("button");
            const actionMenuLabel = I18n.t("admin.datagrid.itemActions");
            toggle.className = "btn btn-light icon-button lm-row-actions-toggle";
            toggle.type = "button";
            toggle.setAttribute("data-lemonade-dropdown-trigger", "");
            toggle.setAttribute("aria-expanded", "false");
            toggle.setAttribute("aria-label", actionMenuLabel);
            toggle.setAttribute("title", actionMenuLabel);
            const icon = document.createElement("i");
            BootstrapIcons.set(icon, "three-dots");
            icon.setAttribute("aria-hidden", "true");
            toggle.appendChild(icon);
            const menu = document.createElement("ul");
            menu.className = "lm-dropdown-menu lm-row-actions-menu";
            menu.setAttribute("data-lemonade-dropdown-panel", "");
            menu.hidden = true;
            const standardActions = menuActions.filter(function (action) {
                return action.placement !== "destructive" && action.risk !== "destructive";
            });
            const destructiveActions = menuActions.filter(function (action) {
                return action.placement === "destructive" || action.risk === "destructive";
            });
            standardActions.forEach(function (action) {
                menu.appendChild(this.createActionMenuItem(action));
            }, this);
            if (standardActions.length && destructiveActions.length) {
                const divider = document.createElement("li");
                const rule = document.createElement("hr");
                rule.className = "lm-dropdown-divider";
                divider.appendChild(rule);
                menu.appendChild(divider);
            }
            destructiveActions.forEach(function (action) {
                menu.appendChild(this.createActionMenuItem(action));
            }, this);
            dropdown.append(toggle, menu);
            actionGroup.appendChild(dropdown);
            cell.appendChild(actionGroup);
            Dropdown.create(dropdown, { placement: "bottom-end", fixed: true });
        }

        createActionMenuItem(action) {
            const item = document.createElement("li");
            const classes = ["lm-dropdown-item"];
            if (action.placement === "destructive" || action.risk === "destructive") {
                classes.push("text-danger");
            } else if (action.risk === "warning") {
                classes.push("text-warning");
            }
            const control = this.createActionControl(action, classes.join(" "));
            const icon = document.createElement("i");
            BootstrapIcons.set(icon, this.actionIcon(action));
            icon.setAttribute("aria-hidden", "true");
            control.prepend(icon);
            item.appendChild(control);

            return item;
        }

        createInlineActionControl(action) {
            return this.createLabeledActionControl(
                action,
                "btn btn-light lm-row-inline-action",
                "data-lemonade-grid-inline-action",
            );
        }

        createPrimaryActionControl(action) {
            return this.createIconActionControl(
                action,
                "btn btn-light icon-button lm-row-primary-action",
                "data-lemonade-grid-primary-action",
            );
        }

        createIconActionControl(action, className, marker) {
            const control = this.createActionControl(action, className);
            control.setAttribute("aria-label", action.ariaLabel || action.label);
            control.setAttribute("title", action.label);
            control.setAttribute(marker, "");
            const icon = document.createElement("i");
            BootstrapIcons.set(icon, this.actionIcon(action));
            icon.setAttribute("aria-hidden", "true");
            control.replaceChildren(icon);

            return control;
        }

        createLabeledActionControl(action, className, marker) {
            const control = this.createActionControl(action, className);
            const accessibleLabel = action.ariaLabel || action.label;
            control.setAttribute("aria-label", accessibleLabel);
            control.setAttribute("title", accessibleLabel);
            control.setAttribute(marker, "");
            const icon = document.createElement("i");
            BootstrapIcons.set(icon, this.actionIcon(action));
            icon.setAttribute("aria-hidden", "true");
            control.replaceChildren(icon, document.createTextNode(action.label));

            return control;
        }

        actionIcon(action) {
            if (BootstrapIcons.isIdentifier(action.icon)) {
                return action.icon;
            }
            const icons = {
                edit: "pencil-square",
                enable: "check-circle",
                disable: "pause-circle",
                delete: "trash3",
                remove: "trash3",
                restore: "arrow-counterclockwise",
                "reset-display": "arrow-counterclockwise",
                open: "box-arrow-up-right",
                detail: "box-arrow-up-right",
                view: "eye",
                features: "sliders",
                settings: "gear",
                install: "boxes",
                "set-default": "star"
            };

            return icons[action.key] || "circle";
        }

        createActionControl(action, className) {
            const kind = action.kind || (action.method === "GET" ? "navigate" : "mutation");
            const legacyModal = !action.kind && action.modalUrl;
            const modal = kind === "modal" || legacyModal;
            const control = kind === "mutation" ? document.createElement("button") : document.createElement("a");
            control.className = className;
            control.textContent = action.label;
            control.setAttribute("aria-label", action.ariaLabel || action.label);
            if (kind === "mutation") {
                control.type = "button";
                control.setAttribute("data-lemonade-action", "");
                if (action.refresh !== false) {
                    control.setAttribute("data-lemonade-grid-refresh", "");
                }
                control.setAttribute("data-lemonade-url", action.url);
                control.setAttribute("data-lemonade-method", action.method);
                control.setAttribute("data-lemonade-payload", JSON.stringify({ action: action.key, payload: {} }));
                if (action.confirm) {
                    control.setAttribute("data-lemonade-confirm", action.confirm);
                    if (action.confirmTitle) {
                        control.setAttribute("data-lemonade-confirm-title", action.confirmTitle);
                    }
                }
            } else {
                control.href = action.url || "#";
            }
            if (modal && action.modalUrl) {
                control.setAttribute("data-lemonade-modal-form-url", action.modalUrl);
                if (action.modalSize) {
                    control.setAttribute("data-lemonade-modal-size", action.modalSize);
                }
                if (action.refresh === true) {
                    control.setAttribute("data-lemonade-modal-refresh-grid", "true");
                }
            }

            return control;
        }

        renderState() {
            this.syncFilterControls();
            this.syncSearchControl();
            if (this.controls.pageSize) { this.controls.pageSize.value = String(this.state.pageSize); }
            DomHelper.queryAll("[data-lemonade-sort]", this.element).forEach(function (element) {
                const column = element.getAttribute("data-lemonade-sort");
                const direction = this.state.sort !== column ? "none" : (this.state.direction === "desc" ? "descending" : "ascending");
                const header = element.closest("th");
                const indicator = element.querySelector("[data-lemonade-sort-indicator]");
                if (header) { header.setAttribute("aria-sort", direction); }
                element.classList.toggle("is-sorted", direction !== "none");
                if (indicator) {
                    indicator.classList.toggle("is-visible", direction !== "none");
                    BootstrapIcons.set(indicator, direction === "descending" ? "arrow-down" : "arrow-up");
                }
            }, this);
            DomHelper.queryAll("[data-lemonade-grid-page]", this.element).forEach(function (element) {
                const isCurrent = Number(element.getAttribute("data-lemonade-grid-page")) === this.state.page;
                const item = DomHelper.closest(element, ".page-item");
                if (item) { item.classList.toggle("active", isCurrent); }
                if (isCurrent) { element.setAttribute("aria-current", "page"); } else { element.removeAttribute("aria-current"); }
            }, this);
            this.renderChips();
            this.renderFilterTriggers();
            this.renderClearFiltersActions();
            this.renderResetAction();
            this.renderSummary();
            this.renderPagination();
            this.renderViews();
            this.updateBulkControls();
        }

        renderClearFiltersActions() {
            const hasActiveFilters = this.hasActiveFilters();
            DomHelper.queryAll("[data-lemonade-grid-clear-filters]", this.element).forEach(function (control) {
                control.hidden = !hasActiveFilters;
            });
        }

        renderThumbnail(thumbnail) {
            const element = document.createElement("span");
            const size = thumbnail?.size === "detail" ? "detail" : "compact";
            const shape = thumbnail?.shape === "rounded" ? "rounded" : "circle";
            element.className = `lm-thumbnail lm-thumbnail--${size} lm-thumbnail--${shape}`;

            if (typeof thumbnail?.url === "string" && thumbnail.url !== "") {
                const image = document.createElement("img");
                image.src = thumbnail.url;
                image.alt = typeof thumbnail.alt === "string" ? thumbnail.alt : "";
                element.append(image);
                return element;
            }

            if (typeof thumbnail?.fallback === "string" && thumbnail.fallback !== "") {
                const fallback = document.createElement("span");
                fallback.setAttribute("aria-hidden", "true");
                fallback.textContent = thumbnail.fallback;
                element.append(fallback);
            }

            return element;
        }

        renderFilterTriggers() {
            const count = Object.keys(this.normalizeFilters(this.state.filters)).length;
            const label = I18n.t("admin.datagrid.filters");
            this.controls.filterTriggers.forEach(function (trigger) {
                trigger.classList.toggle("is-active", count > 0);
                trigger.setAttribute("aria-label", label);
                trigger.setAttribute("title", label);
                const badge = trigger.querySelector("[data-lemonade-grid-filter-count]");
                if (badge) {
                    badge.textContent = String(count);
                    badge.hidden = count === 0;
                }
            });
        }

        renderResetAction() {
            if (this.controls.reset) {
                this.controls.reset.hidden = !this.hasModifiedPreferences();
            }
        }

        applyView(element) {
            this.synchronizePendingSearch();
            const state = this.getStateForView(element, this.cloneState(this.state));
            state.page = 1;
            this.update(state);
        }

        getStateForView(element, state) {
            const query = this.parseViewQuery(element);
            state.viewQuery = Object.assign({}, query);
            state.activeView = element.getAttribute("data-lemonade-grid-view") || "";
            return state;
        }

        getViewQueryNames() {
            const names = new Set();
            this.controls.views.forEach(function (view) {
                this.parseViewQuery(view, names);
            }, this);
            return Array.from(names);
        }

        parseViewQuery(element, names) {
            const query = {};
            const parameters = new URLSearchParams(element.getAttribute("data-lemonade-grid-query") || "");
            parameters.forEach(function (value, name) {
                query[name] = value;
                if (names) { names.add(name); }
            });
            return query;
        }

        getManagedQueryNames() {
            const names = [this.searchParam, this.sortParam, this.directionParam, this.pageParam, this.pageSizeParam].concat(this.viewQueryNames);
            this.controls.filters.forEach(function (control) { names.push(control.getAttribute("data-lemonade-filter")); });
            return Array.from(new Set(names));
        }

        getViewForState(state) {
            let matchingView = "";
            let matchingLength = -1;
            this.controls.views.forEach(function (view) {
                const query = this.parseViewQuery(view);
                const matches = Object.keys(query).every(function (name) {
                    return state.viewQuery[name] === query[name];
                });
                if (matches && Object.keys(query).length > matchingLength) {
                    matchingView = view.getAttribute("data-lemonade-grid-view") || "";
                    matchingLength = Object.keys(query).length;
                }
            }, this);
            return matchingView;
        }

        renderViews() {
            this.controls.views.forEach(function (view) {
                const isActive = view.getAttribute("data-lemonade-grid-view") === this.state.activeView;
                view.classList.toggle("is-active", isActive);
                view.setAttribute("aria-current", isActive ? "page" : "false");
            }, this);
            this.renderViewsMore();
            this.scheduleViewsOverflow();
        }

        renderViewsMore() {
            if (!this.controls.viewsMore || !this.controls.viewsMoreLabel) {
                return;
            }

            const activeView = this.viewItems.find(function (record) {
                return record.item.parentNode === this.controls.viewsMoreMenu
                    && record.view.getAttribute("data-lemonade-grid-view") === this.state.activeView;
            }, this);

            this.controls.viewsMore.classList.toggle("is-active", Boolean(activeView));
            this.setViewsMoreLabel(activeView ? activeView.view : null);
        }

        setViewsMoreLabel(activeView) {
            if (!this.controls.viewsMoreLabel) {
                return;
            }

            const defaultLabel = I18n && I18n.t("admin.datagrid.more") !== "admin.datagrid.more"
                ? I18n.t("admin.datagrid.more")
                : "More";
            this.controls.viewsMoreLabel.textContent = activeView
                ? `${defaultLabel}: ${this.getViewLabel(activeView)}`
                : defaultLabel;
        }

        getViewLabel(view) {
            const label = view.querySelector("[data-lemonade-grid-view-label]");
            return (label || view).textContent.trim();
        }

        renderSummary() {
            if (!this.controls.summary || !this.pagination) { return; }
            const total = Number(this.pagination.total) || 0;
            const page = Number(this.pagination.page) || 1;
            const pageSize = Number(this.pagination.perPage) || this.state.pageSize;
            const values = {
                from: total ? ((page - 1) * pageSize) + 1 : 0,
                to: Math.min(page * pageSize, total),
                total: total
            };
            this.controls.summary.textContent = I18n.t("admin.datagrid.summary", values);
        }

        renderPagination() {
            if (!this.controls.pagination || !this.pagination) { return; }
            const page = Number(this.pagination.page) || 1;
            const pages = Number(this.pagination.pages) || 1;
            const pageNumbers = this.getPaginationPages(page, pages);
            this.controls.pagination.replaceChildren();
            this.controls.pagination.appendChild(this.createPaginationItem("‹", Math.max(1, page - 1), page === 1, I18n.t("admin.datagrid.previous")));
            pageNumbers.forEach(function (number) {
                if (number === null) {
                    const ellipsis = document.createElement("li");
                    ellipsis.className = "page-item disabled";
                    const ellipsisLink = document.createElement("span");
                    ellipsisLink.className = "page-link";
                    ellipsisLink.textContent = "…";
                    ellipsis.appendChild(ellipsisLink);
                    this.controls.pagination.appendChild(ellipsis);
                    return;
                }
                this.controls.pagination.appendChild(this.createPaginationItem(String(number), number, false, number === page));
            }, this);
            this.controls.pagination.appendChild(this.createPaginationItem("›", Math.min(pages, page + 1), page === pages, I18n.t("admin.datagrid.next")));
        }

        getPaginationPages(page, pages) {
            if (pages <= 7) {
                return Array.from({ length: pages }, function (value, index) { return index + 1; });
            }
            const visible = [1];
            if (page > 4) { visible.push(null); }
            for (let number = Math.max(2, page - 1); number <= Math.min(pages - 1, page + 1); number += 1) {
                visible.push(number);
            }
            if (page < pages - 3) { visible.push(null); }
            visible.push(pages);
            return visible;
        }

        createPaginationItem(label, page, isDisabled, isActive, ariaLabel) {
            const item = document.createElement("li");
            const link = document.createElement("a");
            item.className = `page-item${isDisabled ? " disabled" : ""}${isActive ? " active" : ""}`;
            link.className = "page-link";
            link.href = "#";
            link.textContent = label;
            if (ariaLabel) { link.setAttribute("aria-label", ariaLabel); }
            link.setAttribute("data-lemonade-grid-page", String(page));
            if (isActive) { link.setAttribute("aria-current", "page"); }
            item.appendChild(link);
            return item;
        }

        renderChips() {
            const container = this.controls.chips;
            if (!container) { return; }
            const fragment = document.createDocumentFragment();
            this.controls.filters.forEach(function (control) {
                const name = control.getAttribute("data-lemonade-filter");
                const value = this.state.filters[name];
                if (this.isEmptyFilterValue(control, value)) { return; }
                const label = control.closest(".lm-filter-group").querySelector(".form-label").textContent;
                const filterLabel = this.getFilterValueLabel(control, value);
                const chipText = `${label}: ${filterLabel}`;
                const chip = document.createElement("button");
                chip.type = "button";
                chip.className = "lm-filter-chip";
                chip.setAttribute("data-lemonade-grid-filter-remove", name);
                chip.setAttribute("aria-label", this.getRemoveFilterLabel(chipText));
                chip.textContent = `${chipText} ×`;
                fragment.appendChild(chip);
            }, this);
            if (fragment.childNodes.length) {
                const clear = document.createElement("button");
                clear.type = "button";
                clear.className = "lm-filter-chips-clear";
                clear.setAttribute("data-lemonade-grid-clear-filters", "");
                clear.textContent = I18n.t("admin.datagrid.clearAllFilters");
                fragment.appendChild(clear);
            }
            container.replaceChildren(fragment);
            container.hidden = !container.children.length;
        }

        getFilterValueLabel(control, value) {
            const option = Array.from(control.options).find(function (candidate) {
                return candidate.value === String(value);
            });
            if (option && option.textContent) {
                return option.textContent;
            }
            const options = this.filterOptions[control.getAttribute("data-lemonade-filter")] || [];
            const definition = options.find(function (candidate) {
                return candidate.value === String(value);
            });
            return definition && definition.label ? definition.label : String(value);
        }

        getRemoveFilterLabel(chipText) {
            const key = "admin.datagrid.removeFilter";
            const translated = I18n.t(key, { filter: chipText });
            return translated === key ? `Remove filter ${chipText}` : translated;
        }

        getStateLabel(key) {
            return I18n.t(`admin.datagrid.${key}`);
        }

        renderDatasetState(element, state) {
            if (!element) {
                return;
            }
            const content = {
                loading: { icon: "arrow-repeat", title: "loadingData", description: null },
                empty: { icon: "inbox", title: "emptyTitle", description: "emptyDescription" },
                noResults: { icon: "search", title: "noResultsTitle", description: "noResultsDescription" },
                error: { icon: "exclamation-triangle", title: "errorTitle", description: "errorDescription" }
            }[state];
            if (!content) {
                return;
            }
            element.className = `lm-grid-state lm-grid-state--${state}`;
            const icon = document.createElement(state === "loading" ? "span" : "i");
            icon.className = state === "loading"
                ? "spinner-border lm-grid-state-spinner"
                : `${BootstrapIcons.className(content.icon, "circle")} lm-grid-state-icon`;
            icon.setAttribute("aria-hidden", "true");
            const copy = document.createElement("div");
            copy.className = "lm-grid-state-copy";
            const title = document.createElement("strong");
            title.textContent = this.getStateLabel(content.title);
            copy.appendChild(title);
            if (content.description) {
                const description = document.createElement("span");
                description.textContent = this.getStateLabel(content.description);
                copy.appendChild(description);
            }
            if (state === "error") {
                const retry = document.createElement("button");
                retry.className = "btn btn-outline-secondary btn-sm";
                retry.type = "button";
                retry.setAttribute("data-lemonade-grid-retry", "");
                retry.textContent = this.getStateLabel("retry");
                copy.appendChild(retry);
                this.controls.retry = retry;
            }
            element.replaceChildren(icon, copy);
        }

        hasActiveSearchOrFilters() {
            return Boolean(this.controls.search && this.state.search) || this.hasActiveFilters();
        }

        setStatus(status) {
            const controls = this.controls;
            const isRequesting = status === "loading";
            const isInitialLoading = isRequesting && !this.hasLoadedData;
            const isRefreshing = isRequesting && this.hasLoadedData;
            const emptyState = this.hasActiveSearchOrFilters() ? "noResults" : "empty";
            this.currentStatus = status;
            this.element.classList.toggle("is-loading", isRequesting);
            this.element.classList.toggle("is-initial-loading", isInitialLoading);
            this.element.classList.toggle("is-refreshing", isRefreshing);
            this.element.classList.toggle("is-empty", status === "empty");
            this.element.classList.toggle("is-error", status === "error");
            this.element.setAttribute("aria-busy", String(isRequesting));
            this.renderDatasetState(controls.loading, "loading");
            this.renderDatasetState(controls.empty, emptyState);
            this.renderDatasetState(controls.error, "error");
            if (controls.busyOverlay) {
                controls.busyOverlay.hidden = !isRefreshing;
                const label = controls.busyOverlay.querySelector(".lm-grid-busy-overlay-label");
                if (label) {
                    label.textContent = this.getStateLabel("loadingData");
                }
            }
            [[controls.loading, isInitialLoading], [controls.empty, status === "empty"], [controls.error, status === "error"]].forEach(function (item) {
                if (item[0]) {
                    item[0].hidden = !item[1];
                }
            });
            [controls.filterApply, controls.reload, controls.pageSize].forEach(function (control) {
                if (control) {
                    control.disabled = false;
                }
            });
            this.updateBulkControls();
        }

        resetSelection() {
            this.selectedIds.clear();
            this.getSelectionControls().forEach(function (control) { control.checked = false; });
            this.controls.selectAll.forEach(function (control) { control.checked = false; control.indeterminate = false; });
            this.updateBulkControls();
        }

        updateBulkControls() {
            const selectedCount = this.selectedIds.size;
            const isDisabled = selectedCount === 0 || this.element.classList.contains("is-loading");
            let hasAvailableActions = false;
            this.controls.bulkActions.forEach(function (action) {
                const isAvailable = this.isBulkActionAvailable(action);
                action.hidden = !isAvailable;
                action.disabled = isDisabled || !isAvailable;
                if (selectedCount && isAvailable) {
                    action.setAttribute("data-lemonade-payload", JSON.stringify({
                        action: action.getAttribute("data-lemonade-action-key"),
                        payload: { ids: Array.from(this.selectedIds, Number) }
                    }));
                } else {
                    action.removeAttribute("data-lemonade-payload");
                }
                hasAvailableActions = hasAvailableActions || isAvailable;
            }, this);
            if (this.controls.selectionActions) { this.controls.selectionActions.hidden = !hasAvailableActions; }
            if (this.controls.selectionBar) { this.controls.selectionBar.hidden = selectedCount === 0; }
            if (this.controls.selectionCount) { this.controls.selectionCount.textContent = I18n.t("admin.datagrid.selectedCount", { count: selectedCount }); }
            const controls = this.getSelectionControls();
            this.controls.selectAll.forEach(function (control) { control.indeterminate = selectedCount > 0 && selectedCount < controls.length; });
        }

        isBulkActionAvailable(action) {
            const views = action.getAttribute("data-lemonade-grid-bulk-views");
            if (!views) {
                return true;
            }
            return views.split(",").map(function (view) { return view.trim(); }).includes(this.state.activeView);
        }

        getSelectionControls() {
            return DomHelper.queryAll("[data-lemonade-grid-select]", this.element);
        }

        prepareSelectionControls() {
            const body = this.element.querySelector("[data-lemonade-grid-body]");
            if (!body) { return; }
            DomHelper.queryAll("input.form-check-input", body).forEach(function (control, index) {
                if (!control.hasAttribute("data-lemonade-grid-select")) {
                    control.setAttribute("data-lemonade-grid-select", "");
                }
                if (!control.value || control.value === "on") {
                    control.value = control.closest("tr").getAttribute("data-lemonade-row-id") || String(index + 1);
                }
            });
        }

        static initAll(context) {
            this.mount(context || document);
        }

        static mount(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-grid]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-grid]", root));
            elements.forEach(function (element) {
                if (element.lemonadeDataGrid) {
                    return;
                }
                const grid = new DataGrid(element);
                element.lemonadeDataGrid = grid;
                void grid.init().catch(function (error) {
                    console.error("Lemonade DataGrid failed to initialize.", error);
                });
            });
        }

        static destroy(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-grid]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-grid]", root));
            elements.forEach(function (element) { element.lemonadeDataGrid?.destroy(); });
        }
    };

    DataGrid.i18nCache = {};

export function mount(root = document) {
    DataGrid.mount(root);
}

export function destroy(root = document) {
    DataGrid.destroy(root);
}
