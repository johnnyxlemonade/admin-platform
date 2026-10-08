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
            if (groupToggle) {
                const allSelected = editable.length > 0 && editableSelected === editable.length;
                groupToggle.checked = allSelected;
                groupToggle.indeterminate = editableSelected > 0 && !allSelected;
                groupToggle.disabled = groupToggle.hasAttribute("data-lemonade-permission-group-disabled") || editable.length === 0;
                groupToggle.setAttribute("aria-label", container.getAttribute("data-lemonade-permission-select-all-label") || "");
            }
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
        updateCounters(root);
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
            if (!container) return;
            const editable = root.getAttribute("data-lemonade-permission-editable") === "true";
            const allLabel = container.getAttribute("data-lemonade-permission-select-all-label") || "";
            const allLabelKey = container.getAttribute("data-lemonade-permission-select-all-label-key") || "";
            const allLabelI18n = allLabelKey ? ' data-lemonade-i18n="' + escapeHtml(allLabelKey) + '"' : "";
            container.innerHTML = (groups || []).map(function (group) {
                const permissions = group.permissions || [];
                const icon = group.icon ? '<i class="lm-permission-group-icon ' + escapeHtml(group.icon) + '" aria-hidden="true"></i>' : "";
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
                return '<section class="lm-permission-group" data-lemonade-permission-group aria-labelledby="' + escapeHtml(groupId) + '">' +
                    '<div class="lm-permission-group-header"><h3 class="lm-permission-group-title" id="' + escapeHtml(groupId) + '">' + icon + '<span' + (group.labelKey ? ' data-lemonade-i18n="' + escapeHtml(group.labelKey) + '"' : "") + ">" + escapeHtml(group.label) + "</span></h3>" +
                    '<div class="lm-permission-group-actions"><span class="lm-permission-group-count" data-lemonade-permission-count data-selected-count="' + selected + '" data-total-count="' + permissions.length + '" aria-live="polite">' + selected + " / " + permissions.length + '</span><span class="lm-permission-group-select-all"><input class="form-check-input lm-permission-group-toggle" id="' + escapeHtml(groupId) + '-all" type="checkbox"' + (selected === permissions.length && permissions.length > 0 ? " checked" : "") + allDisabled + disabledMarker + ' data-lemonade-permission-group-toggle aria-controls="' + escapeHtml(groupId) + '-permissions"><label class="form-check-label" for="' + escapeHtml(groupId) + '-all"' + allLabelI18n + '>' + escapeHtml(allLabel) + "</label></span></div></div>" +
                    '<div class="lm-permission-grid" id="' + escapeHtml(groupId) + '-permissions">' + items + "</div></section>";
            }).join("");
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
