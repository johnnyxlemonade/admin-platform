/*!
 * Lemonade Tabs
 *
 * Coordinates accessible Admin tab panels without Bootstrap runtime dependencies.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
export class Tabs {
    constructor(root) {
        this.root = root;
        this.tabs = [];
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
        this.tabs = [];
        delete this.root.lemonadeTabs;
        this.destroyed = true;
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
