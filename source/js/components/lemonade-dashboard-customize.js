/*!
 * Lemonade Dashboard Customize
 *
 * Owns current-user widget catalog and layout preference interactions.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Message } from "./lemonade-message.js";
import { Modal } from "./lemonade-modal.js";
import { Sortable } from "./lemonade-sortable.js";
    export class DashboardCustomize {
        constructor(element) {
            this.element = element;
            this.source = element.getAttribute("data-lemonade-dashboard-source");
            this.mutationSource = element.getAttribute("data-lemonade-dashboard-mutation-source");
            this.customizing = false;
            this.mutating = false;
            this.catalog = [];
            this.modal = null;
            this.unbind = [];
        }

        init() {
            if (!this.source || !this.mutationSource) {
                return false;
            }
            this.unbind.push(EventHelper.on(this.element, "click", (event) => {
                void this.handleClick(event);
            }));
            this.unbind.push(EventHelper.on(this.element, "lemonade:sortable:change", (event) => {
                void this.handleSortableChange(event);
            }));
            this.unbind.push(EventHelper.on(document, "lemonade:locale:changed", () => {
                void this.reloadCatalog(false);
            }));

            return true;
        }

        destroy() {
            this.unbind.forEach(function (unbind) {
                unbind();
            });
            this.unbind = [];
            this.modal?.destroy();
            this.modal = null;
            this.modalElement = null;
        }

        async handleClick(event) {
            const customize = event.target.closest("[data-lemonade-dashboard-customize]");
            const done = event.target.closest("[data-lemonade-dashboard-customize-done]");
            const add = event.target.closest("[data-lemonade-dashboard-add-widget]");
            const pin = event.target.closest("[data-lemonade-dashboard-catalog-pin]");
            const remove = event.target.closest("[data-lemonade-dashboard-widget-remove]");
            const size = event.target.closest("[data-lemonade-dashboard-widget-size]");
            const move = event.target.closest("[data-lemonade-dashboard-widget-move]");

            if (customize) {
                this.setCustomize(true);
            } else if (done) {
                this.setCustomize(false);
            } else if (add) {
                await this.openCatalog();
            } else if (pin) {
                await this.mutate({ action: "pin", widgetCode: pin.getAttribute("data-lemonade-dashboard-catalog-pin") });
                this.hideCatalog();
            } else if (remove) {
                const widget = remove.closest("[data-lemonade-dashboard-widget]");
                if (widget) {
                    await this.mutate({ action: "unpin", widgetCode: widget.getAttribute("data-lemonade-widget-code") });
                }
            } else if (size) {
                const widget = size.closest("[data-lemonade-dashboard-widget]");
                if (widget) {
                    await this.mutate({
                        action: "resize",
                        widgetCode: widget.getAttribute("data-lemonade-widget-code"),
                        size: size.getAttribute("data-lemonade-dashboard-widget-size")
                    });
                }
            } else if (move) {
                const widget = move.closest("[data-lemonade-dashboard-widget]");
                if (widget) {
                    await this.move(widget, move.getAttribute("data-lemonade-dashboard-widget-move"));
                }
            } else {
                return;
            }

            event.preventDefault();
        }

        setCustomize(enabled) {
            this.customizing = enabled === true;
            this.element.classList.toggle("is-customizing", this.customizing);
            const toolbar = this.element.querySelector("[data-lemonade-dashboard-customize-toolbar]");
            if (toolbar) {
                toolbar.hidden = !this.customizing;
            }
            const customizeTrigger = this.element.querySelector("[data-lemonade-dashboard-customize-trigger]");
            if (customizeTrigger) {
                customizeTrigger.hidden = this.customizing;
            }
            DomHelper.queryAll("[data-lemonade-dashboard-widget-customize-actions]", this.element).forEach(function (actions) {
                actions.hidden = !this.customizing;
            }, this);
            const grid = this.element.querySelector(".lm-dashboard-widget-grid");
            if (grid) {
                if (this.customizing) {
                    grid.setAttribute("data-lemonade-sortable", "");
                    grid.setAttribute("data-lemonade-sortable-group", "dashboard-widgets");
                    Sortable.initAll(grid);
                } else {
                    grid.removeAttribute("data-lemonade-sortable");
                    grid.removeAttribute("data-lemonade-sortable-group");
                }
            }
        }

        async handleSortableChange(event) {
            if (!this.customizing || !event.detail || !Array.isArray(event.detail.orderedIds)) {
                return;
            }
            await this.mutate({ action: "reorder", widgetCodes: event.detail.orderedIds });
        }

        async move(widget, direction) {
            const items = this.items();
            const index = items.indexOf(widget);
            const target = direction === "up" ? index - 1 : index + 1;
            if (index < 0 || target < 0 || target >= items.length) {
                return;
            }
            const ordered = items.map(function (item) {
                return item.getAttribute("data-lemonade-widget-code");
            });
            const current = ordered[index];
            ordered[index] = ordered[target];
            ordered[target] = current;
            await this.mutate({ action: "reorder", widgetCodes: ordered });
        }

        async openCatalog() {
            if (!await this.reloadCatalog()) {
                return;
            }
            this.ensureModal();
            this.renderCatalog();
            this.modal.open({
                opener: this.element.querySelector("[data-lemonade-dashboard-customize-trigger]"),
                initialFocus: () => this.modalElement.querySelector("[data-lemonade-dashboard-catalog-close]"),
            });
        }

        async reloadCatalog(syncLayout) {
            if (!this.source) {
                return;
            }
            try {
                const response = await HttpHelper.get(this.source);
                await this.ensureTranslations(response);
                this.catalog = Array.isArray(response.catalog) ? response.catalog : [];
                if (syncLayout !== false && response.widgetAreaHtml) {
                    this.applyCanonical(response);
                }
                if (this.modal) {
                    this.renderCatalog();
                }
                return true;
            } catch (error) {
                Message.show({ type: "error", key: "admin.message.error" });
                return false;
            }
        }

        ensureModal() {
            if (this.modal) {
                return;
            }
            this.modal = Modal.create({ size: "medium", scrollable: true, kind: "dashboard-catalog" });
            this.modal.setContent('<header class="lm-modal-header"><h2 class="lm-modal-title" id="lemonade-dashboard-widget-catalog-title" data-lemonade-modal-title data-lemonade-dashboard-catalog-title></h2><button class="lm-modal-close" type="button" data-lemonade-modal-close="close" data-lemonade-dashboard-catalog-close></button></header><section class="lm-modal-body" data-lemonade-modal-body data-lemonade-dashboard-catalog-list></section>');
            this.modalElement = this.modal.element;
            EventHelper.on(this.modalElement, "click", function (event) {
                void (async function () {
                const pin = event.target.closest("[data-lemonade-dashboard-catalog-pin]");
                if (!pin) {
                    return;
                }
                event.preventDefault();
                await this.mutate({ action: "pin", widgetCode: pin.getAttribute("data-lemonade-dashboard-catalog-pin") });
                this.hideCatalog();
                }).call(this);
            }.bind(this));
        }

        renderCatalog() {
            const list = this.modalElement.querySelector("[data-lemonade-dashboard-catalog-list]");
            const title = this.modalElement.querySelector("[data-lemonade-dashboard-catalog-title]");
            const close = this.modalElement.querySelector("[data-lemonade-dashboard-catalog-close]");
            if (title) {
                title.textContent = I18n.t("admin.dashboard.widgets.add");
            }
            if (close) {
                close.setAttribute("aria-label", I18n.t("admin.common.close"));
            }
            if (!list) {
                return;
            }
            if (this.catalog.length === 0) {
                list.innerHTML = '<div class="lm-dashboard-widget-catalog-empty"><i class="' + BootstrapIcons.className("grid-1x2") + '" aria-hidden="true"></i><p data-lemonade-i18n="admin.dashboard.widgets.noAvailable">' + this.escape(I18n.t("admin.dashboard.widgets.noAvailable")) + "</p></div>";
                return;
            }
            list.innerHTML = this.catalog.map(function (widget) {
                const state = widget.managed ? "managed" : (widget.pinned ? "pinned" : "available");
                const labelKey = state === "available" ? "admin.dashboard.widgets.add" : (state === "managed" ? "admin.dashboard.widgets.managed" : "admin.dashboard.widgets.added");
                const disabled = state === "available" ? "" : " disabled";
                const icon = typeof widget.icon === "string" && widget.icon ? '<i class="' + BootstrapIcons.className(widget.icon, "circle") + '" aria-hidden="true"></i>' : "";
                const description = typeof widget.descriptionKey === "string" && widget.descriptionKey ? I18n.t(widget.descriptionKey) : "";
                const title = typeof widget.titleKey === "string" ? I18n.t(widget.titleKey) : widget.title;
                return '<article class="lm-dashboard-widget-catalog-item"><span class="lm-dashboard-widget-catalog-icon">' + icon + '</span><div><strong data-lemonade-i18n="' + this.escapeAttribute(widget.titleKey) + '">' + this.escape(title) + '</strong>' + (description ? '<p data-lemonade-i18n="' + this.escapeAttribute(widget.descriptionKey) + '">' + this.escape(description) + "</p>" : "") + '</div><button class="btn btn-light btn-sm" type="button" data-lemonade-dashboard-catalog-pin="' + this.escapeAttribute(widget.code) + '"' + disabled + ' data-lemonade-i18n="' + labelKey + '">' + this.escape(I18n.t(labelKey)) + "</button></article>";
            }, this).join("");
        }

        hideCatalog() {
            if (this.modal) {
                this.modal.close({ reason: "close" });
            }
        }

        async mutate(payload) {
            if (this.mutating) {
                return;
            }
            this.mutating = true;
            try {
                const response = await HttpHelper.post(this.mutationSource, payload);
                await this.ensureTranslations(response);
                this.catalog = Array.isArray(response.catalog) ? response.catalog : this.catalog;
                this.applyCanonical(response);
            } catch (error) {
                await this.reloadCatalog();
                Message.error(I18n.t("admin.message.error"));
            } finally {
                this.mutating = false;
            }
        }

        applyCanonical(response) {
            if (!response || typeof response.widgetAreaHtml !== "string") {
                return;
            }
            const current = this.element.querySelector("[data-lemonade-dashboard-widget-area]");
            if (!current) {
                return;
            }
            current.outerHTML = response.widgetAreaHtml;
            this.setCustomize(this.customizing);
            const runtime = this.element.lemonadeDashboardWidgets;
            if (runtime) {
                void runtime.loadAll(false);
            }
        }

        async ensureTranslations(response) {
            if (!response || !window.I18n) {
                return;
            }
            await I18n.ensureNamespaces(response.translationResources, I18n.getLocale());
        }

        items() {
            const grid = this.element.querySelector(".lm-dashboard-widget-grid");
            return grid ? Array.from(grid.querySelectorAll(":scope > [data-lemonade-dashboard-widget]")) : [];
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
            const triggers = root.matches && root.matches("[data-lemonade-dashboard-customize]") ? [root] : [];
            triggers.push(...DomHelper.queryAll("[data-lemonade-dashboard-customize]", root));
            const elements = new Set(triggers.map(function (trigger) {
                return trigger.closest("[data-lemonade-dashboard-widgets]");
            }).filter(function (element) {
                return element
                    && element.hasAttribute("data-lemonade-dashboard-source")
                    && element.hasAttribute("data-lemonade-dashboard-mutation-source");
            }));
            elements.forEach(function (element) {
                if (!element.lemonadeDashboardCustomize) {
                    const customize = new DashboardCustomize(element);
                    if (customize.init()) {
                        element.lemonadeDashboardCustomize = customize;
                    }
                }
            });
        }

        static destroy(root = document) {
            const triggers = root.matches && root.matches("[data-lemonade-dashboard-customize]") ? [root] : [];
            triggers.push(...DomHelper.queryAll("[data-lemonade-dashboard-customize]", root));
            new Set(triggers.map(function (trigger) {
                return trigger.closest("[data-lemonade-dashboard-widgets]");
            }).filter(Boolean)).forEach(function (element) {
                element.lemonadeDashboardCustomize?.destroy();
                delete element.lemonadeDashboardCustomize;
            });
        }
    };

export function mount(root = document) {
    DashboardCustomize.mount(root);
}

export function destroy(root = document) {
    DashboardCustomize.destroy(root);
}
