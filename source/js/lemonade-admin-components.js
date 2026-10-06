/*!
 * Lemonade Admin Components
 *
 * Loads Admin component modules only when their existing DOM hooks are present.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { Message } from "./components/lemonade-message.js";

const loadedModules = new Map();

const registry = [
    { name: "Dropdown", selector: "[data-lemonade-dropdown]", load: () => import("./components/lemonade-dropdown.js") },
    { name: "Tabs", selector: "[data-lemonade-tabs]", load: () => import("./components/lemonade-tabs.js") },
    { name: "DataGrid", selector: "[data-lemonade-grid]", load: () => import("./components/lemonade-datagrid.js") },
    { name: "Select", selector: "select.form-select", load: () => import("./components/lemonade-select.js") },
    { name: "Conditional", selector: "[data-lemonade-conditional]", load: () => import("./components/lemonade-conditional.js") },
    { name: "Form editor", selector: "form[data-lemonade-editor-form]", load: () => import("./components/lemonade-form-editor.js") },
    { name: "Language preset", selector: "[data-lemonade-language-preset-select]", load: () => import("./components/lemonade-language-preset.js") },
    { name: "Dirty state", selector: "[data-lemonade-admin-editor]", load: () => import("./components/lemonade-form-dirty-state.js") },
    { name: "Action", selector: "[data-lemonade-action], [data-lemonade-grid]", load: () => import("./components/lemonade-action.js") },
    { name: "Editor lock", selector: "[data-lemonade-action-form]", load: () => import("./components/lemonade-editor-lock.js") },
    { name: "Permission overrides", selector: "[data-lemonade-permission-groups-root]", load: () => import("./components/lemonade-permission-overrides.js") },
    { name: "File upload collection", selector: "[data-lemonade-file-upload-collection]", load: () => import("./components/lemonade-file-upload.js") },
    { name: "Notifications", selector: "[data-lemonade-notifications]", load: () => import("./components/lemonade-notifications.js") },
    { name: "Notification audience", selector: "[data-lemonade-notification-audience]", load: () => import("./components/lemonade-notification-audience.js") },
    { name: "Password toggle", selector: "[data-lemonade-password-toggle]", load: () => import("./components/lemonade-password-toggle.js") },
    { name: "Sortable", selector: "[data-lemonade-sortable]", load: () => import("./components/lemonade-sortable.js") },
    { name: "Dashboard widgets", selector: "[data-lemonade-dashboard-widgets]", load: () => import("./components/lemonade-dashboard-widgets.js") },
    { name: "Dashboard customize", selector: "[data-lemonade-dashboard-customize]", load: () => import("./components/lemonade-dashboard-customize.js") },
    { name: "Clock", selector: "[data-lemonade-clock]", load: () => import("./components/lemonade-clock.js") },
];

const hasHook = (root, selector) => Boolean(root?.matches?.(selector) || root?.querySelector?.(selector));

async function moduleFor(entry) {
    if (!loadedModules.has(entry)) {
        loadedModules.set(entry, entry.load());
    }

    return loadedModules.get(entry);
}

function mountModule(module, root) {
    module.mount(root);
}

export async function mountComponents(root = document) {
    for (const entry of registry) {
        if (!hasHook(root, entry.selector)) {
            continue;
        }

        try {
            mountModule(await moduleFor(entry), root);
        } catch (error) {
            console.error(`Lemonade Admin ${entry.name} module failed to load.`, error);
            Message.show({ type: "error", key: "admin.message.error" });
        }
    }
}

export async function destroyComponents(root = document) {
    for (const entry of registry) {
        if (!loadedModules.has(entry) || !hasHook(root, entry.selector)) {
            continue;
        }

        try {
            const module = await loadedModules.get(entry);
            if (typeof module.destroy === "function") {
                await module.destroy(root);
            }
        } catch (error) {
            console.error(`Lemonade Admin ${entry.name} module failed to destroy.`, error);
        }
    }
}
