/*!
 * Lemonade Permission Overrides
 *
 * Keeps permission dependencies and group controls declarative.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Message } from "./lemonade-message.js";
import { EventHelper } from "../core/lemonade-event-helper.js";

const mountedRoots = new Set();
let rebindListenerRemover = null;

    const escapeHtml = function (value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[character];
        });
    };

    const isInherited = function (state) {
        return String(state || "").indexOf("inherited_") === 0;
    };

    const isAllowedState = function (state) {
        return state === "allow" || state === "inherited_allow";
    };

    const permissionStateLabel = function (container, state) {
        const attributes = {
            inherited_allow: "data-lemonade-permission-inherited-allow-label",
            inherited_deny: "data-lemonade-permission-inherited-deny-label",
            allow: "data-lemonade-permission-allow-label",
            deny: "data-lemonade-permission-deny-label"
        };

        return container.getAttribute(attributes[state]) || state;
    };

    const permissionToggles = function (root) {
        return Array.prototype.slice.call(root.querySelectorAll("[data-lemonade-permission-toggle]"));
    };

    const findPermissionToggle = function (root, code) {
        return permissionToggles(root).find(function (toggle) {
            return toggle.getAttribute("data-lemonade-permission-code") === code;
        }) || null;
    };

    const requiredCodes = function (toggle) {
        const value = toggle.getAttribute("data-lemonade-permission-requires") || "";

        return value === "" ? [] : value.split(",").filter(Boolean);
    };

    const updateSource = function (container, toggle) {
        const item = toggle.closest("[data-lemonade-permission-item]");
        if (!item || container.getAttribute("data-lemonade-permission-mode") !== "user") return;

        const state = toggle.getAttribute("data-lemonade-permission-state") || (toggle.checked ? "allow" : "deny");
        const source = item.querySelector("[data-lemonade-permission-source]");
        const stateLabel = item.querySelector("[data-lemonade-permission-state-label]");
        const inherited = isInherited(state);
        const sourceLabel = inherited
            ? container.getAttribute("data-lemonade-permission-role-label")
            : container.getAttribute("data-lemonade-permission-override-label");

        if (source) {
            source.className = "lm-permission-source " + (inherited ? "is-inherited" : "is-override");
            source.innerHTML = (inherited ? '<i class="bi bi-lock" aria-hidden="true"></i>' : "") +
                '<span data-lemonade-permission-source-label>' + escapeHtml(sourceLabel) + "</span>";
        }
        if (stateLabel) {
            stateLabel.textContent = permissionStateLabel(container, state);
            stateLabel.setAttribute("data-lemonade-i18n", "users.editor.permission_" + state);
        }
    };

    const syncToggle = function (root, toggle) {
        const container = root.querySelector("[data-lemonade-permission-groups]");
        if (!container) return;

        if (container.getAttribute("data-lemonade-permission-mode") === "user") {
            const hidden = toggle.previousElementSibling;
            const inheritedState = toggle.getAttribute("data-lemonade-permission-inherited-state");
            const inherited = isInherited(inheritedState);
            const state = inherited && toggle.checked === isAllowedState(inheritedState)
                ? inheritedState
                : (toggle.checked ? "allow" : "deny");

            toggle.setAttribute("data-lemonade-permission-state", state);
            if (hidden && hidden.hasAttribute("data-lemonade-permission-input")) {
                hidden.value = inherited && state === inheritedState ? "" : (toggle.checked ? "1" : "0");
                hidden.disabled = inherited && state === inheritedState;
            }
            updateSource(container, toggle);
        }
    };

    const setToggle = function (root, toggle, checked) {
        toggle.checked = checked;
        syncToggle(root, toggle);
    };

    const ensurePrerequisites = function (root, toggle, visiting) {
        const code = toggle.getAttribute("data-lemonade-permission-code") || "";
        const path = visiting || {};
        if (path[code]) return true;
        path[code] = true;

        for (const requiredCode of requiredCodes(toggle)) {
            const required = findPermissionToggle(root, requiredCode);
            if (!required) continue;
            if (required.disabled && !required.checked) return false;
            if (!required.checked) setToggle(root, required, true);
            if (!ensurePrerequisites(root, required, path)) return false;
        }

        delete path[code];
        return true;
    };

    const dependentsOf = function (root, code) {
        return permissionToggles(root).filter(function (toggle) {
            return requiredCodes(toggle).indexOf(code) !== -1 && toggle.checked;
        });
    };

    const canClearDependents = function (root, code, visiting) {
        const path = visiting || {};
        if (path[code]) return true;
        path[code] = true;
        for (const dependent of dependentsOf(root, code)) {
            if (dependent.disabled || !canClearDependents(root, dependent.getAttribute("data-lemonade-permission-code") || "", path)) {
                delete path[code];
                return false;
            }
        }
        delete path[code];
        return true;
    };

    const clearDependents = function (root, code, visiting) {
        const path = visiting || {};
        if (path[code]) return;
        path[code] = true;
        dependentsOf(root, code).forEach(function (dependent) {
            const dependentCode = dependent.getAttribute("data-lemonade-permission-code") || "";
            setToggle(root, dependent, false);
            clearDependents(root, dependentCode, path);
        });
        delete path[code];
    };

    const requestToggle = function (root, toggle, checked) {
        if (toggle.disabled) return;
        if (checked) {
            const previousStates = permissionToggles(root).map(function (item) {
                return { toggle: item, checked: item.checked };
            });
            setToggle(root, toggle, true);
            if (!ensurePrerequisites(root, toggle)) {
                previousStates.forEach(function (state) { setToggle(root, state.toggle, state.checked); });
            }
            return;
        }
        const code = toggle.getAttribute("data-lemonade-permission-code") || "";
        if (!canClearDependents(root, code)) {
            setToggle(root, toggle, true);
            return;
        }
        setToggle(root, toggle, false);
        clearDependents(root, code);
    };

    const setGroupExpanded = function (group, expanded) {
        const button = group.querySelector("[data-lemonade-permission-group-collapse]");
        const body = group.querySelector("[data-lemonade-permission-group-body]");
        if (!button || !body) return;

        button.setAttribute("aria-expanded", String(expanded));
        body.hidden = !expanded;
    };

    const expandableGroups = function (root) {
        return Array.prototype.filter.call(root.querySelectorAll("[data-lemonade-permission-group]"), function (group) {
            return !group.hidden;
        });
    };

    const updateExpandCollapseControl = function (root) {
        const button = root.querySelector("[data-lemonade-permission-toggle-all]");
        if (!button) return;

        const groups = expandableGroups(root);
        const allExpanded = groups.length > 0 && groups.every(function (group) {
            return group.querySelector("[data-lemonade-permission-group-collapse]")?.getAttribute("aria-expanded") === "true";
        });
        const fallbackLabel = allExpanded
            ? button.getAttribute("data-lemonade-permission-collapse-label") || ""
            : button.getAttribute("data-lemonade-permission-expand-label") || "";
        const labelKey = allExpanded ? "admin.permissions.collapse_all" : "admin.permissions.expand_all";
        const translatedLabel = I18n ? I18n.t(labelKey) : "";
        const label = translatedLabel && translatedLabel !== labelKey ? translatedLabel : fallbackLabel;
        const labelElement = button.querySelector("[data-lemonade-permission-toggle-all-label]");
        const expandIcon = button.querySelector("[data-lemonade-permission-expand-icon]");
        const collapseIcon = button.querySelector("[data-lemonade-permission-collapse-icon]");

        button.disabled = groups.length === 0;
        button.setAttribute("aria-label", label);
        button.setAttribute("data-lemonade-permission-all-expanded", String(allExpanded));
        if (labelElement) {
            labelElement.textContent = label;
            labelElement.setAttribute("data-lemonade-i18n", labelKey);
        }
        if (expandIcon) expandIcon.hidden = allExpanded;
        if (collapseIcon) collapseIcon.hidden = !allExpanded;
    };

    const normalizeSearchText = function (value) {
        return String(value == null ? "" : value)
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLocaleLowerCase();
    };

    const applyGroupFilter = function (root) {
        const filter = root.querySelector("[data-lemonade-permission-filter]");
        const query = normalizeSearchText(filter?.value).trim();
        const filtering = query !== "";
        let visibleGroups = 0;

        root.querySelectorAll("[data-lemonade-permission-section]").forEach(function (section) {
            let sectionVisible = false;
            section.querySelectorAll("[data-lemonade-permission-group]").forEach(function (group) {
                const groupCopy = group.querySelector(".lm-permission-group-copy");
                const headingMatches = filtering && normalizeSearchText(groupCopy?.textContent).includes(query);
                let permissionMatches = false;
                group.querySelectorAll("[data-lemonade-permission-item]").forEach(function (item) {
                    const label = item.querySelector(".form-check-label");
                    const itemMatches = filtering && normalizeSearchText(label?.textContent).includes(query);
                    permissionMatches = permissionMatches || itemMatches;
                    item.hidden = filtering && !headingMatches && !itemMatches;
                });
                const matches = !filtering || headingMatches || permissionMatches;
                group.hidden = !matches;
                sectionVisible = sectionVisible || matches;
                if (matches) visibleGroups += 1;

                const button = group.querySelector("[data-lemonade-permission-group-collapse]");
                if (filtering) {
                    if (button && !button.hasAttribute("data-lemonade-permission-expanded-before-filter")) {
                        button.setAttribute("data-lemonade-permission-expanded-before-filter", button.getAttribute("aria-expanded") || "false");
                    }
                    setGroupExpanded(group, matches);
                } else if (button?.hasAttribute("data-lemonade-permission-expanded-before-filter")) {
                    setGroupExpanded(group, button.getAttribute("data-lemonade-permission-expanded-before-filter") === "true");
                    button.removeAttribute("data-lemonade-permission-expanded-before-filter");
                }
            });
            section.hidden = !sectionVisible;
        });

        const empty = root.querySelector("[data-lemonade-permission-empty]");
        if (empty) empty.hidden = visibleGroups > 0;
        updateExpandCollapseControl(root);
    };

    const clearGroupFilter = function (root) {
        const filter = root.querySelector("[data-lemonade-permission-filter]");
        if (filter) filter.value = "";
        applyGroupFilter(root);
    };

    const setAllGroupsExpanded = function (root, expanded) {
        expandableGroups(root).forEach(function (group) {
            setGroupExpanded(group, expanded);
        });
        updateExpandCollapseControl(root);
    };

    const groupForModuleCode = function (root, moduleCode) {
        return Array.prototype.find.call(root.querySelectorAll("[data-lemonade-permission-group]"), function (group) {
            return group.getAttribute("data-lemonade-permission-module-code") === moduleCode;
        }) || null;
    };

    const renderModuleOverview = function (root) {
        const list = root.querySelector("[data-lemonade-permission-module-list]");
        if (!list) return;

        list.innerHTML = Array.prototype.map.call(root.querySelectorAll("[data-lemonade-permission-section]"), function (section) {
            const scope = section.getAttribute("data-lemonade-permission-section-scope") || "";
            const sectionLabel = section.querySelector(".lm-permission-section-heading h3");
            const sectionLabelKey = sectionLabel?.getAttribute("data-lemonade-i18n") || "";
            const sectionCounter = section.querySelector("[data-lemonade-permission-section-count]");
            const sectionLabelAttribute = sectionLabelKey ? ' data-lemonade-i18n="' + escapeHtml(sectionLabelKey) + '"' : "";
            const modules = Array.prototype.map.call(section.querySelectorAll("[data-lemonade-permission-group]"), function (group) {
                const moduleCode = group.getAttribute("data-lemonade-permission-module-code") || "";
                const label = group.querySelector(".lm-permission-group-title > span");
                const labelKey = label?.getAttribute("data-lemonade-i18n") || "";
                const icon = group.querySelector(".lm-permission-group-icon i");
                const counter = group.querySelector("[data-lemonade-permission-count]");
                const iconMarkup = icon ? '<i class="' + escapeHtml(icon.className) + '" aria-hidden="true"></i>' : "";
                const labelAttribute = labelKey ? ' data-lemonade-i18n="' + escapeHtml(labelKey) + '"' : "";

                return '<button class="lm-permission-module-link" type="button" data-lemonade-permission-module-link="' + escapeHtml(moduleCode) + '">' +
                    '<span class="lm-permission-module-link-icon">' + iconMarkup + '</span><span class="lm-permission-module-link-label"' + labelAttribute + '>' + escapeHtml(label?.textContent || moduleCode) + '</span>' +
                    '<span class="lm-permission-module-link-count" data-lemonade-permission-module-count>' + escapeHtml(counter?.textContent || "0 / 0") + "</span></button>";
            }).join("");

            return '<section class="lm-permission-module-section" data-lemonade-permission-module-section="' + escapeHtml(scope) + '">' +
                '<header class="lm-permission-module-section-header"><h4' + sectionLabelAttribute + '>' + escapeHtml(sectionLabel?.textContent || scope) + '</h4>' +
                '<span class="lm-permission-module-section-count" data-lemonade-permission-module-section-count>' + escapeHtml(sectionCounter?.textContent || "0 / 0") + "</span></header>" +
                '<div class="lm-permission-module-section-items">' + modules + "</div></section>";
        }).join("");

        list.querySelectorAll("[data-lemonade-permission-module-link]").forEach(function (button) {
            button.addEventListener("click", function () {
                clearGroupFilter(root);
                const group = groupForModuleCode(root, button.getAttribute("data-lemonade-permission-module-link") || "");
                if (!group) return;
                setGroupExpanded(group, true);
                updateExpandCollapseControl(root);
                group.querySelector("[data-lemonade-permission-group-collapse]")?.focus({ preventScroll: true });
                group.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        });
    };

    const applyAll = function (root, checked) {
        const toggles = permissionToggles(root).filter(function (toggle) { return !toggle.disabled; });
        const ordered = checked ? toggles : toggles.slice().reverse();
        ordered.forEach(function (toggle) {
            if (toggle.checked !== checked) requestToggle(root, toggle, checked);
        });
        updateCounters(root);
        root.dispatchEvent(new CustomEvent("lemonade:dirty-state:resync", { bubbles: true }));
    };

    const updateCounters = function (root) {
        const container = root.querySelector("[data-lemonade-permission-groups]");
        if (!container) return;
        root.querySelectorAll("[data-lemonade-permission-group]").forEach(function (group) {
            const toggles = group.querySelectorAll("[data-lemonade-permission-toggle]");
            const selected = Array.prototype.filter.call(toggles, function (toggle) { return toggle.checked; }).length;
            const total = toggles.length;
            const counter = group.querySelector("[data-lemonade-permission-count]");
            const groupToggle = group.querySelector("[data-lemonade-permission-group-toggle]");
            const editable = Array.prototype.filter.call(toggles, function (toggle) { return !toggle.disabled; });
            const editableSelected = editable.filter(function (toggle) { return toggle.checked; }).length;
            if (counter) {
                counter.textContent = selected + " / " + total;
                counter.setAttribute("data-selected-count", String(selected));
                counter.setAttribute("data-total-count", String(total));
                const label = container.getAttribute("data-lemonade-permission-count-label") || "";
                counter.setAttribute("aria-label", label + (label ? ": " : "") + selected + " / " + total);
            }
            const moduleCode = group.getAttribute("data-lemonade-permission-module-code") || "";
            root.querySelectorAll("[data-lemonade-permission-module-link]").forEach(function (link) {
                if (link.getAttribute("data-lemonade-permission-module-link") !== moduleCode) return;
                const moduleCounter = link.querySelector("[data-lemonade-permission-module-count]");
                if (moduleCounter) moduleCounter.textContent = selected + " / " + total;
            });
            if (groupToggle) {
                const allSelected = editable.length > 0 && editableSelected === editable.length;
                groupToggle.checked = allSelected;
                groupToggle.indeterminate = editableSelected > 0 && !allSelected;
                groupToggle.disabled = groupToggle.hasAttribute("data-lemonade-permission-group-disabled") || editable.length === 0;
                groupToggle.setAttribute("aria-label", container.getAttribute("data-lemonade-permission-select-all-label") || "");
            }
        });
        root.querySelectorAll("[data-lemonade-permission-section]").forEach(function (section) {
            const toggles = section.querySelectorAll("[data-lemonade-permission-toggle]");
            const selected = Array.prototype.filter.call(toggles, function (toggle) { return toggle.checked; }).length;
            const total = toggles.length;
            const counter = section.querySelector("[data-lemonade-permission-section-count]");
            const sectionToggle = section.querySelector("[data-lemonade-permission-section-toggle]");
            const editable = Array.prototype.filter.call(toggles, function (toggle) { return !toggle.disabled; });
            const editableSelected = editable.filter(function (toggle) { return toggle.checked; }).length;
            if (counter) {
                counter.textContent = selected + " / " + total;
                counter.setAttribute("data-selected-count", String(selected));
                counter.setAttribute("data-total-count", String(total));
                const label = container.getAttribute("data-lemonade-permission-count-label") || "";
                counter.setAttribute("aria-label", label + (label ? ": " : "") + selected + " / " + total);
            }
            const scope = section.getAttribute("data-lemonade-permission-section-scope") || "";
            root.querySelectorAll("[data-lemonade-permission-module-section]").forEach(function (overviewSection) {
                if (overviewSection.getAttribute("data-lemonade-permission-module-section") !== scope) return;
                const overviewCounter = overviewSection.querySelector("[data-lemonade-permission-module-section-count]");
                if (overviewCounter) overviewCounter.textContent = selected + " / " + total;
            });
            if (sectionToggle) {
                const allSelected = editable.length > 0 && editableSelected === editable.length;
                sectionToggle.checked = allSelected;
                sectionToggle.indeterminate = editableSelected > 0 && !allSelected;
                sectionToggle.disabled = sectionToggle.hasAttribute("data-lemonade-permission-section-disabled") || editable.length === 0;
                sectionToggle.setAttribute("aria-label", container.getAttribute("data-lemonade-permission-select-all-label") || "");
            }
        });
        const toggles = permissionToggles(root);
        const selected = toggles.filter(function (toggle) { return toggle.checked; }).length;
        const editable = toggles.filter(function (toggle) { return !toggle.disabled; });
        root.querySelectorAll("[data-lemonade-permission-total-selected]").forEach(function (counter) {
            counter.textContent = String(selected);
        });
        root.querySelectorAll("[data-lemonade-permission-total]").forEach(function (counter) {
            counter.textContent = String(toggles.length);
        });
        root.querySelectorAll("[data-lemonade-permission-progress]").forEach(function (progress) {
            progress.max = Math.max(1, toggles.length);
            progress.value = selected;
        });
        root.querySelectorAll("[data-lemonade-permission-select-all], [data-lemonade-permission-clear-all]").forEach(function (button) {
            button.disabled = button.hasAttribute("data-lemonade-permission-controls-disabled") || editable.length === 0;
        });
    };

    const bindToggles = function (root) {
        const container = root.querySelector("[data-lemonade-permission-groups]");
        if (!container) return;

        container.querySelectorAll("[data-lemonade-permission-toggle]").forEach(function (toggle) {
            if (toggle.dataset.lemonadePermissionBound) return;
            toggle.dataset.lemonadePermissionBound = "true";
            toggle.addEventListener("change", function () {
                requestToggle(root, toggle, toggle.checked);
                updateCounters(root);
            });
        });
        container.querySelectorAll("[data-lemonade-permission-group-toggle]").forEach(function (groupToggle) {
            if (groupToggle.dataset.lemonadePermissionBound) return;
            groupToggle.dataset.lemonadePermissionBound = "true";
            groupToggle.addEventListener("change", function () {
                const group = groupToggle.closest("[data-lemonade-permission-group]");
                if (!group) return;
                const toggles = Array.prototype.filter.call(group.querySelectorAll("[data-lemonade-permission-toggle]"), function (toggle) {
                    return !toggle.disabled;
                });
                if (groupToggle.checked) {
                    toggles.forEach(function (toggle) { requestToggle(root, toggle, true); });
                } else {
                    toggles.slice().reverse().forEach(function (toggle) {
                        if (toggle.checked) requestToggle(root, toggle, false);
                    });
                }
                updateCounters(root);
            });
        });
        container.querySelectorAll("[data-lemonade-permission-section-toggle]").forEach(function (sectionToggle) {
            if (sectionToggle.dataset.lemonadePermissionBound) return;
            sectionToggle.dataset.lemonadePermissionBound = "true";
            sectionToggle.addEventListener("change", function () {
                const section = sectionToggle.closest("[data-lemonade-permission-section]");
                if (!section) return;
                const toggles = Array.prototype.filter.call(section.querySelectorAll("[data-lemonade-permission-toggle]"), function (toggle) {
                    return !toggle.disabled;
                });
                if (sectionToggle.checked) {
                    toggles.forEach(function (toggle) { requestToggle(root, toggle, true); });
                } else {
                    toggles.slice().reverse().forEach(function (toggle) {
                        if (toggle.checked) requestToggle(root, toggle, false);
                    });
                }
                updateCounters(root);
            });
        });
        container.querySelectorAll("[data-lemonade-permission-group-collapse]").forEach(function (button) {
            const group = button.closest("[data-lemonade-permission-group]");
            if (button.dataset.lemonadePermissionBound) return;
            if (group) setGroupExpanded(group, false);
            button.dataset.lemonadePermissionBound = "true";
            button.addEventListener("click", function () {
                const currentGroup = button.closest("[data-lemonade-permission-group]");
                if (currentGroup) {
                    setGroupExpanded(currentGroup, button.getAttribute("aria-expanded") !== "true");
                    updateExpandCollapseControl(root);
                }
            });
        });
        const filter = root.querySelector("[data-lemonade-permission-filter]");
        if (filter && !filter.dataset.lemonadePermissionBound) {
            filter.dataset.lemonadePermissionBound = "true";
            filter.addEventListener("input", function () { applyGroupFilter(root); });
        }
        const toggleAll = root.querySelector("[data-lemonade-permission-toggle-all]");
        if (toggleAll && !toggleAll.dataset.lemonadePermissionBound) {
            toggleAll.dataset.lemonadePermissionBound = "true";
            toggleAll.addEventListener("click", function () {
                setAllGroupsExpanded(root, toggleAll.getAttribute("data-lemonade-permission-all-expanded") !== "true");
            });
        }
        const selectAll = root.querySelector("[data-lemonade-permission-select-all]");
        if (selectAll && !selectAll.dataset.lemonadePermissionBound) {
            selectAll.dataset.lemonadePermissionBound = "true";
            selectAll.addEventListener("click", function () { applyAll(root, true); });
        }
        const clearAll = root.querySelector("[data-lemonade-permission-clear-all]");
        if (clearAll && !clearAll.dataset.lemonadePermissionBound) {
            clearAll.dataset.lemonadePermissionBound = "true";
            clearAll.addEventListener("click", function () { applyAll(root, false); });
        }
        root.querySelectorAll("[data-lemonade-permission-controls]").forEach(function (controls) {
            controls.hidden = false;
        });
        renderModuleOverview(root);
        updateCounters(root);
        applyGroupFilter(root);
    };

    const permissionGroupMarkup = function (container, group, editable, allLabel, allLabelI18n) {
        const permissions = group.permissions || [];
        const icon = group.icon ? '<span class="lm-permission-group-icon"><i class="' + escapeHtml(group.icon) + '" aria-hidden="true"></i></span>' : "";
        const groupId = "permission-group-" + String(group.moduleCode).replace(/[^a-z0-9_-]+/gi, "-");
        const items = permissions.map(function (permission) {
            const allowed = permission.state === "allow" || permission.state === "inherited_allow";
            const inherited = isInherited(permission.state);
            const id = "permission-" + String(permission.code).replace(/[^a-z0-9_-]+/g, "-");
            const sourceLabel = inherited
                ? container.getAttribute("data-lemonade-permission-role-label")
                : container.getAttribute("data-lemonade-permission-override-label");
            const stateLabel = permissionStateLabel(container, permission.state);
            const requires = Array.isArray(permission.requires) ? permission.requires.filter(function (code) { return typeof code === "string"; }).join(",") : "";
            return '<div class="lm-permission-item" data-lemonade-permission-item>' +
                '<input type="hidden" name="permission_overrides[' + escapeHtml(permission.code) + ']" value="' + (inherited ? "" : escapeHtml(permission.state)) + '"' + (inherited ? " disabled" : "") + ' data-lemonade-permission-input>' +
                '<input class="form-check-input" id="' + escapeHtml(id) + '" type="checkbox"' + (allowed ? " checked" : "") + (editable ? "" : " disabled") + ' data-lemonade-permission-toggle data-lemonade-permission-code="' + escapeHtml(permission.code) + '" data-lemonade-permission-requires="' + escapeHtml(requires) + '"' + (inherited ? ' data-lemonade-permission-inherited-state="' + escapeHtml(permission.state) + '"' : "") + ' data-lemonade-permission-state="' + escapeHtml(permission.state) + '" aria-describedby="' + escapeHtml(id) + '-state">' +
                '<label class="form-check-label" for="' + escapeHtml(id) + '" data-lemonade-i18n="' + escapeHtml(permission.labelKey) + '">' + escapeHtml(permission.label) + "</label>" +
                '<span class="lm-permission-source ' + (inherited ? "is-inherited" : "is-override") + '" data-lemonade-permission-source>' + (inherited ? '<i class="bi bi-lock" aria-hidden="true"></i>' : "") + '<span data-lemonade-permission-source-label>' + escapeHtml(sourceLabel) + "</span></span>" +
                '<span class="visually-hidden" id="' + escapeHtml(id) + '-state" data-lemonade-permission-state-label data-lemonade-i18n="users.editor.permission_' + escapeHtml(permission.state) + '">' + escapeHtml(stateLabel) + "</span>" +
                "</div>";
        }).join("");
        const selected = permissions.filter(function (permission) {
            return permission.state === "allow" || permission.state === "inherited_allow";
        }).length;
        const allDisabled = editable ? "" : " disabled";
        const disabledMarker = editable ? "" : " data-lemonade-permission-group-disabled";
        const description = typeof group.description === "string" && group.description !== ""
            ? '<span class="lm-permission-group-description"' + (group.descriptionKey ? ' data-lemonade-i18n="' + escapeHtml(group.descriptionKey) + '"' : "") + ">" + escapeHtml(group.description) + "</span>"
            : "";
        return '<section class="lm-permission-group" data-lemonade-permission-group data-lemonade-permission-module-code="' + escapeHtml(group.moduleCode) + '" aria-labelledby="' + escapeHtml(groupId) + '">' +
            '<div class="lm-permission-group-header"><button class="lm-permission-group-heading" type="button" data-lemonade-permission-group-collapse aria-expanded="false" aria-controls="' + escapeHtml(groupId) + '-permissions">' + icon + '<span class="lm-permission-group-copy"><span class="lm-permission-group-title" id="' + escapeHtml(groupId) + '"><span' + (group.labelKey ? ' data-lemonade-i18n="' + escapeHtml(group.labelKey) + '"' : "") + ">" + escapeHtml(group.label) + "</span></span>" + description + '</span><i class="bi bi-chevron-down lm-permission-group-chevron" aria-hidden="true"></i></button>' +
            '<div class="lm-permission-group-actions"><span class="lm-permission-group-count" data-lemonade-permission-count data-selected-count="' + selected + '" data-total-count="' + permissions.length + '" aria-live="polite">' + selected + " / " + permissions.length + '</span><span class="lm-permission-group-select-all"><input class="form-check-input lm-permission-group-toggle" id="' + escapeHtml(groupId) + '-all" type="checkbox"' + (selected === permissions.length && permissions.length > 0 ? " checked" : "") + allDisabled + disabledMarker + ' data-lemonade-permission-group-toggle aria-controls="' + escapeHtml(groupId) + '-permissions"><label class="form-check-label" for="' + escapeHtml(groupId) + '-all"' + allLabelI18n + ">" + escapeHtml(allLabel) + "</label></span></div></div>" +
            '<div class="lm-permission-group-body" id="' + escapeHtml(groupId) + '-permissions" data-lemonade-permission-group-body hidden><div class="lm-permission-grid">' + items + "</div></div></section>";
    };

    const permissionSectionMarkup = function (container, scope, groups, editable, allLabel, allLabelI18n) {
        if (groups.length === 0) return "";
        const permissions = groups.flatMap(function (group) { return group.permissions || []; });
        const selected = permissions.filter(function (permission) {
            return permission.state === "allow" || permission.state === "inherited_allow";
        }).length;
        const sectionId = "permission-section-" + scope;
        const title = container.getAttribute("data-lemonade-permission-" + scope + "-title") || "";
        const description = container.getAttribute("data-lemonade-permission-" + scope + "-description") || "";
        const icon = scope === "system" ? "bi-gear" : "bi-boxes";
        const allDisabled = editable ? "" : " disabled data-lemonade-permission-section-disabled";
        return '<section class="lm-permission-section" data-lemonade-permission-section data-lemonade-permission-section-scope="' + escapeHtml(scope) + '" aria-labelledby="' + sectionId + '">' +
            '<header class="lm-permission-section-header"><div class="lm-permission-section-heading"><span class="lm-permission-section-icon"><i class="bi ' + icon + '" aria-hidden="true"></i></span><div><h3 id="' + sectionId + '">' + escapeHtml(title) + "</h3><p>" + escapeHtml(description) + "</p></div></div>" +
            '<div class="lm-permission-section-actions"><span class="lm-permission-section-count" data-lemonade-permission-section-count data-selected-count="' + selected + '" data-total-count="' + permissions.length + '" aria-live="polite">' + selected + " / " + permissions.length + '</span><span class="lm-permission-section-select-all"><input class="form-check-input" id="' + sectionId + '-all" type="checkbox"' + (selected === permissions.length && permissions.length > 0 ? " checked" : "") + allDisabled + ' data-lemonade-permission-section-toggle aria-controls="' + sectionId + '-groups"><label class="form-check-label" for="' + sectionId + '-all"' + allLabelI18n + ">" + escapeHtml(allLabel) + "</label></span></div></header>" +
            '<div class="lm-permission-section-groups" id="' + sectionId + '-groups">' + groups.map(function (group) {
                return permissionGroupMarkup(container, group, editable, allLabel, allLabelI18n);
            }).join("") + "</div></section>";
    };

export const PermissionOverrides = {
        initAll: function (context) {
            const scope = context || document;
            const roots = scope.matches && scope.matches("[data-lemonade-permission-groups-root]") ? [scope] : [];
            roots.push(...scope.querySelectorAll("[data-lemonade-permission-groups-root]"));
            roots.forEach(function (root) {
                mountedRoots.add(root);
                let state = root.lemonadePermissionOverrides;
                if (!state) {
                    state = { controller: null, listenerRemovers: [], requestId: 0 };
                    root.lemonadePermissionOverrides = state;
                }
                const role = root.closest("form")?.querySelector("[data-lemonade-permission-role]")
                    || document.querySelector("[data-lemonade-permission-role]");
                if (role && root.getAttribute("data-lemonade-preview-url") && state.listenerRemovers.length === 0) {
                    state.listenerRemovers.push(EventHelper.on(role, "change", function () {
                        void (async function () {
                        if (!role.value) return;
                        state.controller?.abort();
                        const controller = new AbortController();
                        const requestId = ++state.requestId;
                        state.controller = controller;
                        try {
                            const response = await HttpHelper.post(root.getAttribute("data-lemonade-preview-url"), {
                                action: root.getAttribute("data-lemonade-preview-action"), payload: { role: role.value }
                            }, { signal: controller.signal });
                            if (requestId === state.requestId && response && response.data && Array.isArray(response.data.permissionGroups)) {
                                PermissionOverrides.render(root, response.data.permissionGroups);
                            }
                        } catch (error) {
                            if (error?.name !== "AbortError" && Message) Message.show({ type: "error", message: "Unable to load permissions." });
                        } finally {
                            if (requestId === state.requestId) {
                                state.controller = null;
                            }
                        }
                        })();
                    }));
                }
                bindToggles(root);
            });
            if (!rebindListenerRemover && mountedRoots.size > 0) {
                rebindListenerRemover = EventHelper.on(document, "lemonade:permission-overrides:rebind", function (event) {
                    PermissionOverrides.initAll(event.target);
                });
            }
        },
        mount: function (root = document) {
            this.initAll(root);
        },
        destroy: function (root = document) {
            const roots = root.matches && root.matches("[data-lemonade-permission-groups-root]") ? [root] : [];
            roots.push(...root.querySelectorAll("[data-lemonade-permission-groups-root]"));
            roots.forEach(function (permissionRoot) {
                const state = permissionRoot.lemonadePermissionOverrides;
                if (!state) {
                    return;
                }
                state.controller?.abort();
                state.requestId += 1;
                state.listenerRemovers.forEach(function (remove) { remove(); });
                delete permissionRoot.lemonadePermissionOverrides;
                mountedRoots.delete(permissionRoot);
            });
            if (mountedRoots.size === 0 && rebindListenerRemover) {
                rebindListenerRemover();
                rebindListenerRemover = null;
            }
        },
        render: function (root, groups) {
            const container = root.querySelector("[data-lemonade-permission-groups]");
            const sections = root.querySelector("[data-lemonade-permission-sections]");
            if (!container || !sections) return;
            const editable = root.getAttribute("data-lemonade-permission-editable") === "true";
            const allLabel = container.getAttribute("data-lemonade-permission-select-all-label") || "";
            const allLabelKey = container.getAttribute("data-lemonade-permission-select-all-label-key") || "";
            const allLabelI18n = allLabelKey ? ' data-lemonade-i18n="' + escapeHtml(allLabelKey) + '"' : "";
            const systemGroups = (groups || []).filter(function (group) {
                return String(group.moduleCode || "").indexOf("system.") === 0;
            });
            const moduleGroups = (groups || []).filter(function (group) {
                return String(group.moduleCode || "").indexOf("system.") !== 0;
            });
            sections.innerHTML = permissionSectionMarkup(container, "system", systemGroups, editable, allLabel, allLabelI18n)
                + permissionSectionMarkup(container, "modules", moduleGroups, editable, allLabel, allLabelI18n);
            bindToggles(root);
            if (I18n) I18n.apply(container);
            root.dispatchEvent(new CustomEvent("lemonade:dirty-state:resync", { bubbles: true }));
        }
    };

export function mount(root = document) {
    PermissionOverrides.mount(root);
}

export function destroy(root = document) {
    PermissionOverrides.destroy(root);
}
