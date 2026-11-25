/*!
 * Lemonade Tabs
 *
 * Coordinates accessible Admin tab panels without Bootstrap runtime dependencies.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Dropdown } from "./lemonade-dropdown.js";

export class Tabs {
    constructor(root) {
        this.root = root;
        this.tabs = [];
        this.tabList = null;
        this.navigation = null;
        this.primaryTabs = null;
        this.overflow = null;
        this.overflowMenu = null;
        this.overflowFrame = null;
        this.resizeObserver = null;
        this.dropdown = null;
        this.listenerRemovers = [];
        this.destroyed = false;
    }

    init() {
        this.tabs = Array.from(this.root.querySelectorAll('[role="tab"]')).filter(function (tab) {
            return tab.closest("[data-lemonade-tabs]") === this.root && this.panelFor(tab);
        }, this);

        if (this.tabs.length === 0) {
            return false;
        }

        const activeTab = this.tabs.find(function (tab) {
            return tab.getAttribute("aria-selected") === "true";
        }) || this.tabs[0];

        this.tabs.forEach(function (tab) {
            this.listenerRemovers.push(
                this.listen(tab, "click", this.handleTabClick.bind(this)),
                this.listen(tab, "keydown", this.handleTabKeydown.bind(this)),
            );
        }, this);
        this.activate(activeTab);
        this.setupOverflow();

        return true;
    }

    activate(tabOrId, { focus = false } = {}) {
        if (this.destroyed) {
            return null;
        }

        const activeTab = this.resolveTab(tabOrId);
        if (!activeTab) {
            return null;
        }

        this.tabs.forEach(function (tab) {
            const isActive = tab === activeTab;
            const panel = this.panelFor(tab);

            tab.classList.toggle("is-active", isActive);
            tab.setAttribute("aria-selected", String(isActive));
            tab.tabIndex = isActive ? 0 : -1;
            panel.hidden = !isActive;
        }, this);

        if (focus) {
            activeTab.focus();
        }

        this.scheduleOverflow();

        return activeTab;
    }

    activeTab() {
        return this.tabs.find(function (tab) {
            return tab.getAttribute("aria-selected") === "true";
        }) || null;
    }

    destroy() {
        if (this.destroyed) {
            return;
        }

        this.listenerRemovers.forEach(function (remove) { remove(); });
        this.listenerRemovers = [];
        this.resizeObserver?.disconnect();
        this.dropdown?.destroy();
        if (this.overflowFrame !== null) {
            window.cancelAnimationFrame(this.overflowFrame);
        }
        this.restoreTabList();
        this.tabs = [];
        delete this.root.lemonadeTabs;
        this.destroyed = true;
    }

    setupOverflow() {
        if (!this.root.hasAttribute("data-lemonade-tabs-overflow")) {
            return;
        }

        this.tabList = this.tabs[0]?.closest('[role="tablist"]') || null;
        if (!this.tabList || this.tabList.parentElement !== this.root) {
            return;
        }

        this.navigation = document.createElement("div");
        this.navigation.className = "lm-tabs-navigation";
        this.primaryTabs = document.createElement("div");
        this.primaryTabs.className = "lm-tabs-primary";
        this.tabList.insertAdjacentElement("beforebegin", this.navigation);
        this.navigation.appendChild(this.primaryTabs);
        this.primaryTabs.appendChild(this.tabList);

        this.overflow = document.createElement("div");
        this.overflow.className = "lm-tabs-overflow";
        this.overflow.hidden = true;

        const trigger = document.createElement("button");
        trigger.className = "lm-tabs-overflow-trigger";
        trigger.type = "button";
        trigger.setAttribute("data-lemonade-dropdown-trigger", "");
        trigger.setAttribute("aria-expanded", "false");

        const label = document.createElement("span");
        const labelKey = "admin.editor.more_sections";
        const translatedLabel = I18n.t(labelKey);
        label.textContent = translatedLabel === labelKey ? "More" : translatedLabel;
        label.setAttribute("data-lemonade-i18n", labelKey);
        trigger.appendChild(label);

        const icon = document.createElement("i");
        BootstrapIcons.set(icon, "chevron-down");
        icon.setAttribute("aria-hidden", "true");
        trigger.appendChild(icon);

        this.overflowMenu = document.createElement("div");
        this.overflowMenu.className = "lm-dropdown-menu lm-tabs-overflow-menu";
        this.overflowMenu.setAttribute("data-lemonade-dropdown-panel", "");
        this.overflowMenu.setAttribute("role", "tablist");
        this.overflowMenu.setAttribute("aria-label", label.textContent);
        this.overflowMenu.setAttribute("data-lemonade-i18n-aria-label", labelKey);
        this.overflowMenu.hidden = true;
        this.overflow.append(trigger, this.overflowMenu);
        this.navigation.appendChild(this.overflow);
        this.dropdown = Dropdown.create(this.overflow, { placement: "bottom-end" });

        if ("ResizeObserver" in window) {
            this.resizeObserver = new ResizeObserver(this.scheduleOverflow.bind(this));
            this.resizeObserver.observe(this.navigation);
        }

        this.scheduleOverflow();
    }

    scheduleOverflow() {
        if (!this.navigation || this.overflowFrame !== null) {
            return;
        }

        this.overflowFrame = window.requestAnimationFrame(function () {
            this.overflowFrame = null;
            this.updateOverflow();
        }.bind(this));
    }

    updateOverflow() {
        if (!this.tabList || !this.primaryTabs || !this.overflow || !this.overflowMenu) {
            return;
        }

        this.tabs.forEach(function (tab) {
            this.moveTab(tab, false);
        }, this);
        this.overflow.hidden = true;

        if (this.tabList.scrollWidth <= this.navigation.clientWidth) {
            return;
        }

        this.overflow.hidden = false;
        this.overflow.style.visibility = "hidden";

        let primaryTabs = this.getPrimaryTabs();
        while (primaryTabs.length > 1 && this.tabList.scrollWidth > this.primaryTabs.clientWidth) {
            const activeTab = this.activeTab();
            const tabToMove = primaryTabs.slice().reverse().find(function (tab) {
                return tab !== activeTab;
            }) || primaryTabs[primaryTabs.length - 1];
            this.moveTab(tabToMove, true);
            primaryTabs = this.getPrimaryTabs();
        }

        this.overflow.style.visibility = "";
        if (!this.overflowMenu.children.length) {
            this.overflow.hidden = true;
        }
    }

    getPrimaryTabs() {
        return this.tabs.filter(function (tab) {
            return tab.parentElement === this.tabList;
        }, this);
    }

    moveTab(tab, toOverflow) {
        tab.classList.toggle("lm-tab", !toOverflow);
        tab.classList.toggle("lm-dropdown-item", toOverflow);
        tab.tabIndex = toOverflow || tab === this.activeTab() ? 0 : -1;

        if (toOverflow) {
            this.overflowMenu.prepend(tab);
            return;
        }

        this.tabList.appendChild(tab);
    }

    restoreTabList() {
        if (!this.tabList || !this.navigation) {
            return;
        }

        this.tabs.forEach(function (tab) {
            tab.classList.add("lm-tab");
            tab.classList.remove("lm-dropdown-item");
            this.tabList.appendChild(tab);
        }, this);
        this.navigation.insertAdjacentElement("beforebegin", this.tabList);
        this.navigation.remove();
        this.tabList = null;
        this.navigation = null;
        this.primaryTabs = null;
        this.overflow = null;
        this.overflowMenu = null;
        this.dropdown = null;
    }

    panelFor(tab) {
        const panelId = tab.getAttribute("aria-controls");

        return panelId ? this.root.ownerDocument.getElementById(panelId) : null;
    }

    resolveTab(tabOrId) {
        if (this.tabs.includes(tabOrId)) {
            return tabOrId;
        }

        return this.tabs.find(function (tab) {
            return tab.id === tabOrId || tab.getAttribute("aria-controls") === tabOrId;
        }) || null;
    }

    handleTabClick(event) {
        event.preventDefault();
        this.activate(event.currentTarget);
    }

    handleTabKeydown(event) {
        const currentIndex = this.tabs.indexOf(event.currentTarget);
        let nextIndex = null;

        if (event.key === "ArrowLeft") {
            nextIndex = currentIndex === 0 ? this.tabs.length - 1 : currentIndex - 1;
        } else if (event.key === "ArrowRight") {
            nextIndex = currentIndex === this.tabs.length - 1 ? 0 : currentIndex + 1;
        } else if (event.key === "Home") {
            nextIndex = 0;
        } else if (event.key === "End") {
            nextIndex = this.tabs.length - 1;
        }

        if (nextIndex === null) {
            return;
        }

        event.preventDefault();
        this.activate(this.tabs[nextIndex], { focus: true });
    }

    listen(element, eventName, callback) {
        element.addEventListener(eventName, callback);

        return function () {
            element.removeEventListener(eventName, callback);
        };
    }

    static create(root) {
        root.lemonadeTabs?.destroy();
        const tabs = new Tabs(root);

        if (!tabs.init()) {
            return null;
        }

        root.lemonadeTabs = tabs;

        return tabs;
    }

    static mount(root = document) {
        const elements = root.matches?.("[data-lemonade-tabs]") ? [root] : [];
        elements.push(...root.querySelectorAll("[data-lemonade-tabs]"));
        elements.forEach(function (element) {
            if (!element.lemonadeTabs) {
                Tabs.create(element);
            }
        });
    }

    static destroy(root = document) {
        const elements = root.matches?.("[data-lemonade-tabs]") ? [root] : [];
        elements.push(...root.querySelectorAll("[data-lemonade-tabs]"));
        elements.forEach(function (element) { element.lemonadeTabs?.destroy(); });
    }
}

export function mount(root = document) {
    Tabs.mount(root);
}

export function destroy(root = document) {
    Tabs.destroy(root);
}
