/*!
 * Lemonade Notifications
 *
 * Loads and manages admin notifications.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { Accordion } from "./lemonade-accordion.js";
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { Confirm } from "./lemonade-confirm.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { LoadingSkeleton } from "./lemonade-loading-skeleton.js";
import { Message } from "./lemonade-message.js";
import { Dropdown } from "./lemonade-dropdown.js";
    export class Notifications {
        constructor(element) {
            this.element = element;
            this.source = element.getAttribute("data-lemonade-source");
            this.indicatorSource = element.getAttribute("data-lemonade-indicator-source");
            this.actionSource = element.getAttribute("data-lemonade-action-source");
            this.toggle = element.querySelector("[data-lemonade-dropdown-trigger]");
            this.list = element.querySelector("[data-lemonade-notifications-list]");
            this.markRead = element.querySelector("[data-lemonade-notifications-mark-read]");
            this.loadMore = element.querySelector("[data-lemonade-notifications-load-more]");
            this.loadMoreSkeleton = element.querySelector("[data-lemonade-notifications-load-more-skeleton]");
            this.viewAll = element.querySelector("[data-lemonade-notifications-view-all]");
            this.footer = element.querySelector("[data-lemonade-notifications-footer]");
            this.isInbox = element.hasAttribute("data-lemonade-notifications-inbox");
            this.items = [];
            this.unread = 0;
            this.hasUnread = false;
            this.hasLoaded = false;
            this.isLoading = false;
            this.isLoadingMore = false;
            this.page = 1;
            this.perPage = this.isInbox ? this.inboxPerPage() : 8;
            this.hasMore = false;
            this.pollInterval = null;
            this.pollDelay = 60000;
            this.refreshController = null;
            this.refreshRequestId = 0;
            this.indicatorController = null;
            this.indicatorRequestId = 0;
            this.stateGeneration = 0;
            this.listenerRemovers = [];
        }

        init() {
            if (!this.source || !this.list) {
                return;
            }

            if (this.toggle) {
                this.dropdown = Dropdown.create(this.element, {
                    placement: "bottom-end",
                    onOpen: () => {
                        void this.open();
                    },
                });
            }

            if (this.markRead) {
                this.listen(this.markRead, "click", (event) => { void this.markAllRead(event); });
            }
            if (this.loadMore) {
                this.listen(this.loadMore, "click", () => { void this.loadMoreItems(); });
            }
            if (this.list) {
                this.listen(this.list, "click", (event) => { void this.handleItemClick(event); });
                this.listen(this.list, "lemonade:accordion:change", (event) => { this.handleInboxAccordionChange(event); });
            }
            this.listen(document, "lemonade:locale:changed", this.reload.bind(this));
            this.listen(document, "lemonade:notifications:refresh", this.handleRefresh.bind(this));
            this.listen(document, "lemonade:action:success", this.handleActionSuccess.bind(this));
            if (this.toggle) {
                this.listen(document, "visibilitychange", this.handleVisibilityChange.bind(this));
            }
            this.renderCount();
            if (this.isInbox) {
                void this.refresh({ force: true, renderList: true, background: false });
            } else {
                void this.refreshIndicator();
            }
            if (this.toggle) {
                this.startPolling();
            }
        }

        async open() {
            await this.refresh({ force: true, renderList: true, background: false });
        }

        async refresh(options) {
            const settings = Object.assign({ force: false, renderList: false, background: false }, options || {});
            if (!settings.force && this.hasLoaded) {
                return;
            }

            if (this.refreshController) {
                this.refreshController.abort();
            }
            const controller = new AbortController();
            const requestId = ++this.refreshRequestId;
            this.stateGeneration += 1;
            this.refreshController = controller;
            this.isLoading = true;
            if (settings.renderList) {
                this.renderInitialLoading();
            }

            try {
                const response = await HttpHelper.get(this.source, { signal: controller.signal });
                if (requestId !== this.refreshRequestId) {
                    return;
                }
                this.items = Array.isArray(response.items) ? response.items : [];
                this.unread = Number(response.unread) || 0;
                this.hasUnread = this.unread > 0;
                this.applyPagination(response.pagination);
                this.hasLoaded = true;
                this.renderCount();
                if (settings.renderList || this.isDropdownOpen()) {
                    this.render();
                }
            } catch (error) {
                if (error && error.name === "AbortError") {
                    return;
                }
                if (requestId !== this.refreshRequestId) {
                    return;
                }
                if (!this.hasLoaded) {
                    this.unread = 0;
                    this.renderCount();
                }
                if (settings.renderList) {
                    this.renderState(I18n.t("admin.notifications.error"));
                    this.element.dispatchEvent(new CustomEvent("lemonade:notifications:error", { bubbles: true, detail: { error: error } }));
                }
            } finally {
                if (requestId === this.refreshRequestId) {
                    this.isLoading = false;
                    this.refreshController = null;
                    if (settings.renderList) {
                        this.element.setAttribute("aria-busy", "false");
                    }
                }
            }
        }

        async refreshIndicator() {
            if (!this.indicatorSource) {
                return;
            }

            if (this.indicatorController) {
                this.indicatorController.abort();
            }
            const controller = new AbortController();
            const requestId = ++this.indicatorRequestId;
            this.indicatorController = controller;

            try {
                const response = await HttpHelper.get(this.indicatorSource, { signal: controller.signal });
                if (requestId !== this.indicatorRequestId) {
                    return;
                }
                this.hasUnread = response && response.hasUnread === true;
                this.renderCount();
            } catch (error) {
                if (error && error.name === "AbortError") {
                    return;
                }
            } finally {
                if (requestId === this.indicatorRequestId) {
                    this.indicatorController = null;
                }
            }
        }

        async loadMoreItems() {
            if (!this.isInbox || !this.hasMore || this.isLoadingMore) {
                return;
            }

            this.isLoadingMore = true;
            const stateGeneration = this.stateGeneration;
            this.renderLoadMore();
            this.renderLoadMoreSkeleton();
            try {
                const response = await HttpHelper.get(this.pageSource(this.page + 1));
                if (stateGeneration !== this.stateGeneration) {
                    return;
                }
                const nextItems = Array.isArray(response.items) ? response.items : [];
                const existingIds = new Set(this.items.map(function (item) { return item.id; }));
                const appended = nextItems.filter(function (item) {
                    return existingIds.has(item.id) === false;
                });
                this.items = this.items.concat(appended);
                this.unread = Number(response.unread) || 0;
                this.hasUnread = this.unread > 0;
                this.applyPagination(response.pagination);
                this.renderCount();
                if (this.list) {
                    appended.forEach(function (item) {
                        this.list.appendChild(this.createItem(item));
                    }, this);
                    this.mountAccordions();
                }
            } catch (error) {
                if (stateGeneration === this.stateGeneration) {
                    Message.error(I18n.t("admin.notifications.error"));
                }
            } finally {
                this.isLoadingMore = false;
                this.renderLoadMore();
                this.renderLoadMoreSkeleton();
            }
        }

        async markAllRead(event) {
            event.preventDefault();
            if (this.unread === 0) {
                return;
            }

            const confirmed = await Confirm.show({
                title: "admin.notifications.markAllReadConfirm.title",
                message: "admin.notifications.markAllReadConfirm.message",
                confirmText: "admin.notifications.markAllReadConfirm.confirm",
                cancelText: "admin.common.cancel",
            }, this.markRead);
            if (!confirmed) {
                return;
            }

            try {
                await HttpHelper.post(this.actionSource, { action: "mark-all-read" });
            } catch (error) {
                Message.error(I18n.t("admin.notifications.error"));
                return;
            }
            Notifications.requestRefresh();
            this.element.dispatchEvent(new CustomEvent("lemonade:notifications:read", { bubbles: true }));
        }

        async handleItemClick(event) {
            if (this.isInbox) {
                return;
            }

            const item = event.target.closest("[data-lemonade-notification-id]");
            if (!item || !this.list || !this.list.contains(item)) {
                return;
            }

            const id = Number(item.getAttribute("data-lemonade-notification-id"));
            const href = item.getAttribute("data-lemonade-notification-url");
            if (!Number.isInteger(id) || id <= 0) {
                return;
            }

            if (item.classList.contains("lm-activity-item--unread") === false) {
                if (!this.isInbox && href) {
                    window.location.assign(href);
                }

                return;
            }

            try {
                const response = await HttpHelper.post(this.actionSource, { action: "mark-read", id: id });
                if (response.success === true) {
                    Notifications.requestRefresh();
                }
            } catch (error) {
                Message.error(I18n.t("admin.notifications.error"));
                return;
            }

            if (href && href !== "#") {
                window.location.assign(href);
            }
        }

        handleInboxAccordionChange(event) {
            if (!this.isInbox || !event.detail || event.detail.expanded !== true) {
                return;
            }

            const item = event.target.closest("[data-lemonade-notification-id]");
            const id = item ? Number(item.getAttribute("data-lemonade-notification-id")) : 0;
            const notification = this.items.find(function (candidate) { return candidate.id === id; });
            if (!item || !notification || notification.read || item.hasAttribute("data-lemonade-notification-marking-read")) {
                return;
            }

            void this.markInboxItemRead(item, id);
        }

        async markInboxItemRead(item, id) {
            item.setAttribute("data-lemonade-notification-marking-read", "");
            this.setInboxItemReadState(item, id, true);
            try {
                const response = await HttpHelper.post(this.actionSource, { action: "mark-read", id: id });
                if (!response || response.success !== true) {
                    throw new Error();
                }
                Notifications.requestRefresh();
            } catch (error) {
                this.setInboxItemReadState(item, id, false);
                Message.error(I18n.t("admin.notifications.error"));
            } finally {
                item.removeAttribute("data-lemonade-notification-marking-read");
            }
        }

        setInboxItemReadState(item, id, read) {
            const current = this.items.find(function (notification) { return notification.id === id; });
            if (!current || current.read === read) {
                return;
            }

            this.items = this.items.map(function (notification) {
                return notification.id === id ? Object.assign({}, notification, { read: read }) : notification;
            });
            this.unread = Math.max(0, this.unread + (read ? -1 : 1));
            this.hasUnread = this.unread > 0;
            item.classList.toggle("lm-activity-item--unread", !read);
            const existingDot = item.querySelector(".lm-activity-unread");
            if (read && existingDot) {
                existingDot.remove();
            }
            if (!read && !existingDot) {
                const trailing = item.querySelector(".lm-notification-trailing");
                const chevron = item.querySelector(".lm-accordion-chevron");
                if (trailing && chevron) {
                    const unreadDot = document.createElement("span");
                    unreadDot.className = "lm-activity-unread";
                    unreadDot.setAttribute("aria-hidden", "true");
                    trailing.insertBefore(unreadDot, chevron);
                }
            }
            this.renderCount();
        }

        reload() {
            this.handleRefresh();
        }

        handleRefresh() {
            if (this.isInbox) {
                void this.refresh({ force: true, renderList: true, background: false });
                return;
            }

            void this.refreshIndicator();
            if (this.isDropdownOpen()) {
                void this.refresh({ force: true, renderList: true, background: false });
            }
        }

        handleActionSuccess(event) {
            if (!this.isNotificationManagementAction(event.target)) {
                return;
            }

            Notifications.requestRefresh();
        }

        isNotificationManagementAction(element) {
            const url = element && element.getAttribute ? element.getAttribute("data-lemonade-url") : null;

            return typeof url === "string" && url.indexOf("/system/notifications/ajax") !== -1;
        }

        listen(target, event, listener) {
            this.listenerRemovers.push(EventHelper.on(target, event, listener));
        }

        startPolling() {
            if (document.visibilityState !== "visible" || this.pollInterval !== null) {
                return;
            }

            this.pollInterval = window.setInterval(function () {
                void this.refreshIndicator();
            }.bind(this), this.pollDelay);
        }

        stopPolling() {
            if (this.pollInterval === null) {
                return;
            }

            window.clearInterval(this.pollInterval);
            this.pollInterval = null;
        }

        destroy() {
            this.stopPolling();
            if (this.refreshController) {
                this.refreshController.abort();
                this.refreshController = null;
            }
            if (this.indicatorController) {
                this.indicatorController.abort();
                this.indicatorController = null;
            }
            this.refreshRequestId += 1;
            this.indicatorRequestId += 1;
            this.stateGeneration += 1;
            this.listenerRemovers.forEach(function (remove) { remove(); });
            this.listenerRemovers = [];
            delete this.element.lemonadeNotifications;
        }

        handleVisibilityChange() {
            if (document.visibilityState !== "visible") {
                this.stopPolling();
                return;
            }

            void this.refreshIndicator();
            if (this.isDropdownOpen()) {
                void this.refresh({ force: true, renderList: true, background: false });
            }
            this.startPolling();
        }

        isDropdownOpen() {
            return this.toggle !== null && this.toggle.getAttribute("aria-expanded") === "true";
        }

        render() {
            this.renderCount();
            if (!this.list) {
                return;
            }
            this.list.replaceChildren();
            if (this.items.length === 0) {
                if (this.isInbox) {
                    this.renderInboxEmpty();
                } else {
                    this.renderState(I18n.t("admin.notifications.empty"));
                }
                this.renderLoadMore();
                return;
            }
            this.items.forEach(function (item) {
                this.list.appendChild(this.createItem(item));
            }, this);
            this.mountAccordions();
            this.renderLoadMore();
        }

        renderInboxEmpty() {
            if (!this.list) {
                return;
            }

            const empty = document.createElement("li");
            const icon = document.createElement("i");
            const message = document.createElement("p");

            empty.className = "lm-notifications-empty";
            icon.className = BootstrapIcons.className("inbox");
            icon.setAttribute("aria-hidden", "true");
            message.setAttribute("data-lemonade-i18n", "admin.notifications.empty");
            message.textContent = I18n.t("admin.notifications.empty");
            empty.append(icon, message);
            this.list.appendChild(empty);
        }

        mountAccordions() {
            if (this.isInbox && Accordion) {
                Accordion.initAll(this.list);
            }
        }

        renderCount() {
            const unread = this.unread;
            DomHelper.queryAll("[data-lemonade-notifications-unread]", this.element).forEach(function (element) {
                element.textContent = I18n.t("admin.notifications.unread", { count: unread });
                element.hidden = unread === 0;
            });
            if (this.toggle) {
                this.toggle.classList.toggle("has-notification", this.hasUnread);
            }
            if (this.markRead) {
                this.markRead.hidden = unread === 0;
            }
            if (this.viewAll) {
                this.viewAll.hidden = this.items.length === 0;
            }
            if (this.footer) {
                this.footer.hidden = this.items.length === 0;
            }
        }

        applyPagination(pagination) {
            const meta = pagination && typeof pagination === "object" ? pagination : {};
            const page = Number(meta.page);
            const perPage = Number(meta.perPage);

            this.page = Number.isInteger(page) && page > 0 ? page : 1;
            this.perPage = Number.isInteger(perPage) && perPage > 0 ? perPage : this.perPage;
            this.hasMore = meta.hasMore === true;
        }

        inboxPerPage() {
            const perPage = Number(this.element.getAttribute("data-lemonade-notifications-per-page"));

            return Number.isInteger(perPage) && perPage > 0 ? perPage : 8;
        }

        pageSource(page) {
            const url = new window.URL(this.source, window.location.origin);
            url.searchParams.set("page", String(page));
            url.searchParams.set("perPage", String(this.perPage));

            return url.pathname + url.search;
        }

        renderLoadMore() {
            if (!this.loadMore) {
                return;
            }

            this.loadMore.hidden = !this.isInbox || !this.hasMore;
            this.loadMore.disabled = this.isLoadingMore;
            this.loadMore.setAttribute("aria-busy", this.isLoadingMore ? "true" : "false");
            this.loadMore.textContent = I18n.t(this.isLoadingMore ? "admin.notifications.loadingMore" : "admin.notifications.loadMore");
        }

        renderInitialLoading() {
            if (!this.list) {
                return;
            }

            this.element.setAttribute("aria-busy", "true");
            this.list.replaceChildren();
            const loading = document.createElement("li");
            loading.className = "lm-notifications-loading";
            loading.innerHTML = LoadingSkeleton.markup({
                variant: "list",
                rows: 4,
                text: I18n.t("admin.notifications.loading"),
                textKey: "admin.notifications.loading",
            });
            this.list.appendChild(loading);
            this.renderLoadMore();
        }

        renderLoadMoreSkeleton() {
            if (!this.loadMoreSkeleton) {
                return;
            }

            this.loadMoreSkeleton.hidden = !this.isLoadingMore;
            this.loadMoreSkeleton.setAttribute("aria-busy", this.isLoadingMore ? "true" : "false");
            this.loadMoreSkeleton.innerHTML = this.isLoadingMore
                ? LoadingSkeleton.markup({
                    variant: "list",
                    rows: 3,
                    text: I18n.t("admin.notifications.loadingMore"),
                    textKey: "admin.notifications.loadingMore",
                })
                : "";
        }

        renderState(message) {
            if (!this.list) {
                return;
            }
            this.list.replaceChildren();
            const state = document.createElement(this.list.tagName === "UL" ? "li" : "p");
            state.className = "lm-notifications-state";
            state.textContent = message;
            this.list.appendChild(state);
        }

        createItem(item) {
            return this.isInbox ? this.createInboxItem(item) : this.createDropdownItem(item);
        }

        createDropdownItem(item) {
            const notification = document.createElement("li");
            const icon = document.createElement("span");
            const iconGlyph = document.createElement("i");
            const content = document.createElement("div");
            const title = document.createElement("p");
            const trailing = document.createElement("span");
            const meta = document.createElement("time");

            notification.className = `lm-activity-item lm-activity-item--stacked lm-notification-item${this.isInbox ? "" : " lm-activity-item--compact"}${item.read ? "" : " lm-activity-item--unread"}`;
            notification.setAttribute("data-lemonade-notification-id", String(item.id));
            if (!this.isInbox) {
                notification.setAttribute("data-lemonade-notification-url", item.url || this.viewAll?.href || "");
            }
            icon.className = "lm-activity-icon";
            iconGlyph.className = BootstrapIcons.className(item.icon, "activity");
            iconGlyph.setAttribute("aria-hidden", "true");
            icon.appendChild(iconGlyph);
            content.className = "lm-activity-content";
            title.className = "lm-activity-message";
            this.renderMessage(content, title, item);
            trailing.className = "lm-activity-trailing";
            meta.className = "lm-activity-time";
            meta.textContent = item.createdAt ? I18n.formatDate(item.createdAt, { dateStyle: "medium", timeStyle: "short" }) : "";
            trailing.appendChild(meta);
            if (item.read === false) {
                const unreadDot = document.createElement("span");
                unreadDot.className = "lm-activity-unread";
                unreadDot.setAttribute("aria-hidden", "true");
                trailing.appendChild(unreadDot);
            }
            notification.appendChild(icon);
            notification.appendChild(content);
            notification.appendChild(trailing);
            return notification;
        }

        createInboxItem(item) {
            const notification = document.createElement("li");
            const toggle = document.createElement("button");
            const icon = document.createElement("span");
            const iconGlyph = document.createElement("i");
            const content = document.createElement("span");
            const author = document.createElement("strong");
            const title = document.createElement("span");
            const trailing = document.createElement("span");
            const meta = document.createElement("time");
            const chevron = document.createElement("i");
            const body = document.createElement("div");
            const bodyText = document.createElement("p");
            const bodyId = "notification-body-" + String(item.id);

            notification.className = `lm-accordion lm-notification-card lm-notification-item${item.read ? "" : " lm-activity-item--unread"}`;
            notification.setAttribute("data-lemonade-accordion", "");
            notification.setAttribute("data-lemonade-notification-id", String(item.id));
            toggle.className = "lm-accordion-trigger lm-notification-toggle";
            toggle.type = "button";
            toggle.setAttribute("data-lemonade-accordion-trigger", "");
            toggle.setAttribute("aria-expanded", "false");
            toggle.setAttribute("aria-controls", bodyId);
            icon.className = "lm-activity-icon";
            iconGlyph.className = BootstrapIcons.className(item.icon, "activity");
            iconGlyph.setAttribute("aria-hidden", "true");
            icon.appendChild(iconGlyph);
            content.className = "lm-notification-content";
            author.className = "lm-activity-author";
            author.textContent = typeof item.author === "string" ? item.author : "";
            title.className = "lm-notification-title";
            title.textContent = item.title || I18n.t("admin.notifications.title");
            content.append(author, title);
            trailing.className = "lm-notification-trailing";
            meta.className = "lm-activity-time";
            meta.textContent = item.createdAt ? I18n.formatDate(item.createdAt, { dateStyle: "medium", timeStyle: "short" }) : "";
            chevron.className = BootstrapIcons.className("chevron-down") + " lm-accordion-chevron";
            chevron.setAttribute("aria-hidden", "true");
            trailing.append(meta, chevron);
            if (item.read === false) {
                const unreadDot = document.createElement("span");
                unreadDot.className = "lm-activity-unread";
                unreadDot.setAttribute("aria-hidden", "true");
                trailing.insertBefore(unreadDot, chevron);
            }
            body.className = "lm-accordion-panel lm-notification-body";
            body.id = bodyId;
            body.setAttribute("data-lemonade-accordion-panel", "");
            body.hidden = true;
            bodyText.textContent = typeof item.message === "string" ? item.message : "";
            body.appendChild(bodyText);
            toggle.append(icon, content, trailing);
            notification.append(toggle, body);

            return notification;
        }

        renderMessage(content, title, item) {
            const author = typeof item.author === "string" ? item.author : "";
            const heading = item.title || I18n.t("admin.notifications.title");
            if (author) {
                const authorElement = document.createElement("strong");
                authorElement.className = "lm-activity-author";
                authorElement.textContent = author;
                content.appendChild(authorElement);
            }
            title.textContent = heading;
            content.appendChild(title);
        }

        static initAll(context) {
            this.mount(context || document);
        }

        static mount(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-notifications]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-notifications]", root));
            elements.forEach(function (element) {
                if (element.lemonadeNotifications) {
                    return;
                }

                const notifications = new Notifications(element);
                element.lemonadeNotifications = notifications;
                notifications.init();
            });
        }

        static destroy(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-notifications]") ? [root] : [];
            elements.push(...DomHelper.queryAll("[data-lemonade-notifications]", root));
            elements.forEach(function (element) { element.lemonadeNotifications?.destroy(); });
        }

        static requestRefresh() {
            document.dispatchEvent(new CustomEvent("lemonade:notifications:refresh"));
        }
    };

export function mount(root = document) {
    Notifications.mount(root);
}

export function destroy(root = document) {
    Notifications.destroy(root);
}
