/*!
 * Lemonade Dashboard Widgets
 *
 * Loads shared dashboard widget content with isolated per-widget states.
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
import { LoadingSkeleton } from "./lemonade-loading-skeleton.js";
    export class DashboardWidgets {
        constructor(element) {
            this.element = element;
            this.inFlight = new Set();
            this.requests = new Map();
            this.generations = new Map();
            this.listenerRemovers = [];
            this.delayTimers = new Map();
            this.destroyed = false;
            this.loadingText = element.getAttribute("data-lemonade-dashboard-loading") || "Loading…";
            this.errorText = element.getAttribute("data-lemonade-dashboard-error") || "Unable to load widget.";
        }

        init() {
            this.element.lemonadeDashboardWidgets = this;
            void this.loadAll();
            this.listenerRemovers.push(EventHelper.on(this.element, "click", (event) => {
                void this.handleClick(event);
            }));
            this.listenerRemovers.push(EventHelper.on(document, "lemonade:locale:changed", () => {
                this.reload();
            }));
        }

        async handleClick(event) {
            const refresh = event.target.closest("[data-lemonade-dashboard-widget-refresh]");
            const retry = event.target.closest("[data-lemonade-dashboard-widget-retry]");
            const trigger = refresh || retry;
            if (!trigger || !this.element.contains(trigger)) {
                return;
            }
            const widget = trigger.closest("[data-lemonade-dashboard-widget]");
            if (!widget) {
                return;
            }
            event.preventDefault();
            await this.load(widget, { minimumDelayMs: refresh !== null ? 300 : 0 });
        }

        reload() {
            void this.loadAll();
        }

        async loadAll() {
            if (this.destroyed) {
                return;
            }

            const widgets = DomHelper.queryAll("[data-lemonade-dashboard-widget]", this.element);

            for (const widget of widgets) {
                await this.load(widget, { minimumDelayMs: 0 });
            }
        }

        async load(widget, { minimumDelayMs = 0 } = {}) {
            if (this.destroyed) {
                return;
            }

            const code = widget.getAttribute("data-lemonade-widget-code");
            const source = widget.getAttribute("data-lemonade-widget-source");
            const body = widget.querySelector("[data-lemonade-dashboard-widget-body]");
            if (!code || !source || !body) {
                return;
            }

            const previous = this.requests.get(code);
            if (previous && previous.controller) {
                previous.controller.abort();
            }
            const generation = (this.generations.get(code) || 0) + 1;
            const locale = I18n.getLocale();
            const controller = typeof window.AbortController === "function" ? new window.AbortController() : null;
            this.generations.set(code, generation);
            this.requests.set(code, { generation: generation, locale: locale, controller: controller });
            this.inFlight.add(code);
            widget.setAttribute("aria-busy", "true");
            widget.classList.add("is-loading");
            const refresh = widget.querySelector("[data-lemonade-dashboard-widget-refresh]");
            if (refresh) {
                refresh.disabled = true;
            }
            body.innerHTML = this.loadingMarkup(widget);
            this.renderFooter(widget, null);

            const minimumDelay = minimumDelayMs > 0 ? this.minimumDelay(minimumDelayMs) : null;
            try {
                const response = await HttpHelper.get(source, controller ? { signal: controller.signal } : undefined);
                if (minimumDelay !== null) {
                    await minimumDelay;
                }
                if (!this.isCurrentRequest(code, generation, locale, widget)) {
                    return;
                }
                this.renderContent(widget, body, response);
            } catch (error) {
                if (minimumDelay !== null) {
                    await minimumDelay;
                }
                if (!this.isCurrentRequest(code, generation, locale, widget)) {
                    return;
                }
                body.innerHTML = this.errorMarkup();
                this.renderFooter(widget, null);
            } finally {
                if (this.isCurrentRequest(code, generation, locale, widget)) {
                    this.requests.delete(code);
                    this.inFlight.delete(code);
                    widget.setAttribute("aria-busy", "false");
                    widget.classList.remove("is-loading");
                    const refresh = widget.querySelector("[data-lemonade-dashboard-widget-refresh]");
                    if (refresh) {
                        refresh.disabled = false;
                    }
                }
            }
        }

        renderContent(widget, body, response) {
            if (response && response.state === "ready" && typeof response.html === "string") {
                body.innerHTML = response.html;
            } else if (response && response.state === "empty") {
                const key = typeof response.emptyMessageKey === "string" ? response.emptyMessageKey : "";
                const message = key ? I18n.t(key) : I18n.t("admin.dashboard.widgets.empty");
                const attribute = key ? ' data-lemonade-i18n="' + this.escapeAttribute(key) + '"' : "";
                body.innerHTML = '<div class="lm-dashboard-widget-state lm-dashboard-widget-state--empty"><i class="' + BootstrapIcons.className("inbox") + '" aria-hidden="true"></i><p' + attribute + '>' + this.escape(message) + "</p></div>";
            } else {
                body.innerHTML = this.errorMarkup();
            }
            widget.setAttribute("data-lemonade-dashboard-widget-ready", "true");
            this.renderFooter(widget, response && response.footer ? response.footer : null);
        }

        isCurrentRequest(code, generation, locale, widget) {
            const request = this.requests.get(code);
            return request && request.generation === generation && request.locale === locale && I18n.getLocale() === locale && this.element.contains(widget);
        }

        renderFooter(widget, footer) {
            const element = widget.querySelector("[data-lemonade-dashboard-widget-footer]");
            if (!element) {
                return;
            }
            if (!footer || typeof footer.url !== "string" || typeof footer.labelKey !== "string") {
                element.hidden = true;
                element.innerHTML = "";
                return;
            }
            element.hidden = false;
            element.innerHTML = '<a href="' + this.escapeAttribute(footer.url) + '" data-lemonade-i18n="' + this.escapeAttribute(footer.labelKey) + '">' + this.escape(I18n.t(footer.labelKey)) + "</a>";
        }

        loadingMarkup(widget) {
            const skeleton = widget.getAttribute("data-lemonade-widget-skeleton") === "stat" ? "stat" : "list";
            return LoadingSkeleton.markup({
                variant: skeleton,
                rows: 4,
                text: this.loadingText,
                textKey: "admin.dashboard.widgets.loading",
            });
        }

        errorMarkup() {
            return '<div class="lm-dashboard-widget-state lm-dashboard-widget-state--error" role="status"><i class="' + BootstrapIcons.className("exclamation-circle") + '" aria-hidden="true"></i><p data-lemonade-i18n="admin.dashboard.widgets.error">' + this.escape(this.errorText) + "</p></div>";
        }

        minimumDelay(minimumDelayMs) {
            return new Promise(function (resolve) {
                const timer = window.setTimeout(() => {
                    this.delayTimers.delete(timer);
                    resolve();
                }, minimumDelayMs);
                this.delayTimers.set(timer, resolve);
            }.bind(this));
        }

        destroy() {
            if (this.destroyed) {
                return;
            }

            this.destroyed = true;
            this.listenerRemovers.forEach(function (remove) { remove(); });
            this.listenerRemovers = [];
            this.delayTimers.forEach(function (resolve, timer) {
                window.clearTimeout(timer);
                resolve();
            });
            this.delayTimers.clear();
            this.requests.forEach(function (request) { request.controller?.abort(); });
            this.requests.clear();
            this.inFlight.clear();
            this.generations.clear();
            delete this.element.lemonadeDashboardWidgets;
            this.element.removeAttribute("data-lemonade-dashboard-widgets-mounted");
        }

        escape(value) {
            const element = document.createElement("span");
            element.textContent = String(value);
            return element.innerHTML;
        }

        escapeAttribute(value) {
            return this.escape(value).replace(/"/g, "&quot;");
        }

        static initAll(context) {
            this.mount(context || document);
        }

        static mount(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-dashboard-widgets]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-dashboard-widgets]", root));
            elements.forEach(function (element) {
                if (!element.hasAttribute("data-lemonade-dashboard-widgets-mounted")) {
                    new DashboardWidgets(element).init();
                    element.setAttribute("data-lemonade-dashboard-widgets-mounted", "");
                }
            });
        }

        static destroy(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-dashboard-widgets]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-dashboard-widgets]", root));
            elements.forEach(function (element) {
                element.lemonadeDashboardWidgets?.destroy();
            });
        }
    };

export function mount(root = document) {
    DashboardWidgets.mount(root);
}

export function destroy(root = document) {
    DashboardWidgets.destroy(root);
}
