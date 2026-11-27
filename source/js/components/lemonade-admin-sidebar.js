/*!
 * Lemonade Admin Sidebar
 *
 * Manages responsive admin sidebar navigation
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { Collapse } from "./lemonade-collapse.js";
import { Dropdown } from "./lemonade-dropdown.js";
import { DomHelper } from "../core/lemonade-dom-helper.js";
import { EventHelper } from "../core/lemonade-event-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { StorageHelper } from "../core/lemonade-storage-helper.js";

/**
 * Manages responsive navigation and its browser-local collapsed preference
 */
export class AdminSidebar {
    /**
     * Creates a sidebar controller for one navigation root
     *
     * @param {HTMLElement} sidebar
     */
    constructor(sidebar) {
        this.sidebar = sidebar;
        this.shell = DomHelper.closest(sidebar, "[data-lemonade-admin-shell]");
        this.toggle = sidebar.querySelector("[data-lemonade-sidebar-toggle]");
        this.dismiss = this.shell?.querySelector("[data-lemonade-sidebar-dismiss]") || null;
        this.mobileMedia = typeof window.matchMedia === "function"
            ? window.matchMedia("(max-width: 767.98px)")
            : null;
        this.storageKey = sidebar.getAttribute("data-lemonade-storage-key") || "lemonade-admin-sidebar";
        this.groups = DomHelper.queryAll("[data-lemonade-sidebar-group]", sidebar);
        this.isCollapsed = false;
        this.listenerRemovers = [];
        this.destroyed = false;
    }

    /**
     * Activates responsive navigation and preference synchronization
     *
     * @returns {boolean}
     */
    init() {
        this.syncCollapsedState(this.getInitialCollapsed());

        if (this.toggle) {
            this.listenerRemovers.push(EventHelper.on(this.toggle, "click", this.handleToggle.bind(this)));
        }

        if (this.dismiss) {
            this.listenerRemovers.push(EventHelper.on(this.dismiss, "click", this.closeMobile.bind(this)));
        }

        if (this.mobileMedia) {
            this.listenerRemovers.push(EventHelper.on(this.mobileMedia, "change", this.handleViewportChange.bind(this)));
        }

        this.listenerRemovers.push(EventHelper.on(document, "keydown", this.handleDocumentKeydown.bind(this)));
        this.listenerRemovers.push(EventHelper.delegate(document, "click", "[data-lemonade-sidebar-option]", this.handlePreference.bind(this)));
        this.groups.forEach(function (group) { this.bindGroup(group); }, this);
        this.configureGroups(this.isCollapsed);
        this.updateMainToggle(this.isCollapsed);
        this.syncPreferenceControls();
        this.listenerRemovers.push(EventHelper.on(document, "lemonade:locale:changed", function () {
            this.updateMainToggle(this.isCollapsed);
        }.bind(this)));
        document.documentElement.classList.add("is-admin-ready");

        return true;
    }

    /**
     * Releases listeners and mounted navigation components
     *
     * @returns {void}
     */
    destroy() {
        if (this.destroyed) {
            return;
        }

        this.listenerRemovers.forEach(function (remove) { remove(); });
        this.listenerRemovers = [];
        this.groups.forEach(function (group) {
            group.lemonadeCollapse?.destroy();
            group.lemonadeDropdown?.destroy();
        });
        delete this.sidebar.lemonadeAdminSidebar;
        this.destroyed = true;
    }

    /**
     * Resolves the initial state without opening the mobile drawer on page load
     *
     * @returns {boolean}
     */
    getInitialCollapsed() {
        if (this.isMobile()) {
            return true;
        }

        const earlyState = document.documentElement.getAttribute("data-lemonade-sidebar-state");

        if (earlyState === "collapsed" || earlyState === "expanded") {
            return earlyState === "collapsed";
        }

        return false;
    }

    /**
     * Reports whether the sidebar currently uses the mobile drawer layout
     *
     * @returns {boolean}
     */
    isMobile() {
        return this.mobileMedia?.matches === true;
    }

    /**
     * Reads the persisted desktop sidebar preference
     *
     * @returns {boolean}
     */
    getStoredCollapsed() {
        return StorageHelper.get(this.storageKey) === true;
    }

    /**
     * Toggles the sidebar and persists the state only on desktop
     *
     * @returns {void}
     */
    handleToggle() {
        this.setCollapsed(!this.isCollapsed, !this.isMobile());
    }

    /**
     * Applies the browser-local sidebar preference from profile controls
     *
     * @param {Event} event
     * @param {HTMLElement} option
     * @returns {void}
     */
    handlePreference(event, option) {
        event.preventDefault();

        const isCollapsed = option.getAttribute("data-lemonade-sidebar-option") === "collapsed";

        StorageHelper.set(this.storageKey, isCollapsed);

        if (!this.isMobile()) {
            this.setCollapsed(isCollapsed, false);
            return;
        }

        this.syncPreferenceControls(isCollapsed);
    }

    /**
     * Closes the mobile drawer and returns focus to its toggle
     *
     * @returns {void}
     */
    closeMobile() {
        if (!this.isMobile() || this.isCollapsed) {
            return;
        }

        this.setCollapsed(true, false);
        this.toggle?.focus();
    }

    /**
     * Closes the mobile drawer with the Escape key
     *
     * @param {KeyboardEvent} event
     * @returns {void}
     */
    handleDocumentKeydown(event) {
        if (event.key !== "Escape" || !this.isMobile() || this.isCollapsed) {
            return;
        }

        event.preventDefault();
        this.closeMobile();
    }

    /**
     * Switches between the temporary mobile state and persisted desktop state
     *
     * @param {MediaQueryListEvent} event
     * @returns {void}
     */
    handleViewportChange(event) {
        this.setCollapsed(event.matches ? true : this.getStoredCollapsed(), false);
    }

    /**
     * Applies sidebar state and optionally persists the desktop preference
     *
     * @param {boolean} isCollapsed
     * @param {boolean} shouldPersist
     * @returns {void}
     */
    setCollapsed(isCollapsed, shouldPersist) {
        this.syncCollapsedState(isCollapsed);
        this.configureGroups(this.isCollapsed);
        this.updateMainToggle(this.isCollapsed);

        if (shouldPersist) {
            StorageHelper.set(this.storageKey, this.isCollapsed);
        }

        this.syncPreferenceControls(shouldPersist || !this.isMobile() ? this.isCollapsed : this.getStoredCollapsed());
    }

    /**
     * Synchronizes every sidebar preference control with persisted state
     *
     * @param {boolean|null} collapsed
     * @returns {void}
     */
    syncPreferenceControls(collapsed = null) {
        const isCollapsed = collapsed === null
            ? (this.isMobile() ? this.getStoredCollapsed() : this.isCollapsed)
            : Boolean(collapsed);

        DomHelper.queryAll("[data-lemonade-sidebar-option]").forEach(function (option) {
            const active = option.getAttribute("data-lemonade-sidebar-option") === (isCollapsed ? "collapsed" : "expanded");
            option.classList.toggle("is-active", active);
            option.setAttribute("aria-checked", String(active));
            DomHelper.queryAll("[data-lemonade-sidebar-check]", option).forEach(function (indicator) {
                indicator.hidden = !active;
            });
        });
    }

    /**
     * Applies collapsed state to the shell and accessibility attributes
     *
     * @param {boolean} isCollapsed
     * @returns {void}
     */
    syncCollapsedState(isCollapsed) {
        this.isCollapsed = Boolean(isCollapsed);
        this.sidebar.classList.toggle("is-collapsed", this.isCollapsed);

        if (this.shell) {
            this.shell.classList.toggle("is-sidebar-collapsed", this.isCollapsed);
        }

        if (this.dismiss) {
            this.dismiss.hidden = !this.isMobile() || this.isCollapsed;
        }

        document.documentElement.setAttribute("data-lemonade-sidebar-state", this.isCollapsed ? "collapsed" : "expanded");
    }

    /**
     * Switches navigation groups between collapse and flyout behavior
     *
     * @param {boolean} isCollapsed
     * @returns {void}
     */
    configureGroups(isCollapsed) {
        this.groups.forEach(function (group) {
            const groupToggle = group.querySelector("[data-lemonade-sidebar-group-toggle]");
            const submenu = group.querySelector("[data-lemonade-sidebar-submenu]");

            if (!groupToggle || !submenu) {
                return;
            }

            const isExpanded = group.lemonadeCollapse?.isOpen() ?? group.classList.contains("is-expanded");

            if (isCollapsed) {
                group.lemonadeCollapse?.destroy();
                group.removeAttribute("data-lemonade-collapse");
                groupToggle.removeAttribute("data-lemonade-collapse-trigger");
                submenu.removeAttribute("data-lemonade-collapse-panel");
                group.setAttribute("data-lemonade-dropdown", "");
                submenu.classList.add("lm-dropdown-menu", "lm-sidebar-flyout");
                DomHelper.queryAll("a", submenu).forEach(function (link) {
                    link.classList.add("lm-dropdown-item");
                });
                groupToggle.setAttribute("data-lemonade-dropdown-trigger", "");
                submenu.setAttribute("data-lemonade-dropdown-panel", "");
                submenu.hidden = true;
                group.classList.toggle("is-expanded", isExpanded);
                groupToggle.setAttribute("aria-expanded", "false");
                Dropdown.create(group, {
                    placement: "right-start",
                    closeOnInsideClick: false,
                    onOpen: function () { this.closeOtherGroups(group); }.bind(this),
                });
                return;
            }

            group.lemonadeDropdown?.destroy();
            group.removeAttribute("data-lemonade-dropdown");
            groupToggle.removeAttribute("data-lemonade-dropdown-trigger");
            submenu.removeAttribute("data-lemonade-dropdown-panel");
            submenu.classList.remove("lm-dropdown-menu", "lm-sidebar-flyout");
            DomHelper.queryAll("a", submenu).forEach(function (link) {
                link.classList.remove("lm-dropdown-item");
            });
            group.setAttribute("data-lemonade-collapse", "");
            groupToggle.setAttribute("data-lemonade-collapse-trigger", "");
            submenu.setAttribute("data-lemonade-collapse-panel", "");
            submenu.hidden = !isExpanded;
            Collapse.mount(group);
            this.setGroupExpanded(group, group.lemonadeCollapse?.isOpen() ?? isExpanded);
        }, this);
    }

    /**
     * Connects expansion state for one navigation group
     *
     * @param {HTMLElement} group
     * @returns {void}
     */
    bindGroup(group) {
        const groupToggle = group.querySelector("[data-lemonade-sidebar-group-toggle]");
        const submenu = group.querySelector("[data-lemonade-sidebar-submenu]");

        if (!groupToggle || !submenu) {
            return;
        }

        this.listenerRemovers.push(EventHelper.on(group, "click", function (event) {
            if (this.isCollapsed || DomHelper.closest(event.target, "[data-lemonade-sidebar-group-toggle]") !== groupToggle) {
                return;
            }

            const isExpanded = group.lemonadeCollapse?.isOpen() === true;

            if (isExpanded) {
                this.closeOtherGroups(group);
            }

            this.setGroupExpanded(group, isExpanded);
        }.bind(this)));
    }

    /**
     * Closes every open navigation group except the newly opened group
     *
     * @param {HTMLElement} activeGroup
     * @returns {void}
     */
    closeOtherGroups(activeGroup) {
        this.groups.forEach(function (group) {
            if (group === activeGroup) {
                return;
            }

            if (this.isCollapsed) {
                group.lemonadeDropdown?.close("switch");
                return;
            }

            if (group.lemonadeCollapse?.isOpen() !== true) {
                return;
            }

            group.lemonadeCollapse.close();
            this.setGroupExpanded(group, false);
        }, this);
    }

    /**
     * Updates one navigation group's expanded presentation
     *
     * @param {HTMLElement} group
     * @param {boolean} isExpanded
     * @returns {void}
     */
    setGroupExpanded(group, isExpanded) {
        const chevron = group.querySelector(".sidebar-nav-chevron");

        group.classList.toggle("is-expanded", isExpanded);

        if (chevron) {
            BootstrapIcons.set(chevron, isExpanded ? "chevron-up" : "chevron-down");
        }
    }

    /**
     * Updates the main sidebar toggle label and icon
     *
     * @param {boolean} isCollapsed
     * @returns {void}
     */
    updateMainToggle(isCollapsed) {
        if (!this.toggle) {
            return;
        }

        const icon = this.toggle.querySelector("[data-lemonade-sidebar-toggle-icon]");

        this.toggle.setAttribute("aria-expanded", String(!isCollapsed));
        this.toggle.setAttribute("aria-label", I18n.t(isCollapsed ? "admin.sidebar.expandNavigation" : "admin.sidebar.collapseNavigation"));

        if (icon) {
            BootstrapIcons.set(icon, isCollapsed ? "chevron-bar-right" : "chevron-bar-left");
        }
    }

    /**
     * Initializes every sidebar not already connected in a context
     *
     * @param {Document|HTMLElement|null} context
     * @returns {void}
     */
    static initAll(context) {
        DomHelper.queryAll("[data-lemonade-sidebar]", context || document).forEach(function (sidebar) {
            if (sidebar.lemonadeAdminSidebar) {
                return;
            }

            const adminSidebar = new AdminSidebar(sidebar);

            if (adminSidebar.init()) {
                sidebar.lemonadeAdminSidebar = adminSidebar;
            }
        });
    }
}
