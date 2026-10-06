/*!
 * Lemonade Admin
 *
 * Initializes the eager Admin runtime without changing its DOM lifecycle.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { AdminSidebar } from "./components/lemonade-admin-sidebar.js";
import { Confirm } from "./components/lemonade-confirm.js";
import { Message } from "./components/lemonade-message.js";
import { ModalForm } from "./components/lemonade-modal-form.js";
import { I18n } from "./core/lemonade-i18n.js";
import { Theme } from "./core/lemonade-theme.js";
import { mountComponents } from "./lemonade-admin-components.js";

function recoverFromStaleAssets() {
    const reloadKey = `lemonade-admin:preload-error:${import.meta.url}`;

    window.addEventListener("vite:preloadError", function (event) {
        if (window.sessionStorage.getItem(reloadKey) === "1") {
            return;
        }

        window.sessionStorage.setItem(reloadKey, "1");
        event.preventDefault();
        window.location.reload();
    });
}

function restoreEditorVersions() {
    document.querySelectorAll("[data-lemonade-editor-version-value]").forEach(function (field) {
        const version = Number(field.getAttribute("data-lemonade-editor-version-value"));
        if (Number.isSafeInteger(version) && version > 0) {
            field.value = String(version);
        }
    });
}

async function initializeAdmin() {
    try {
        await I18n.init(document.querySelector("[data-lemonade-admin-shell]"));
    } catch (error) {
        // HTML fallback text remains available when translation assets cannot be loaded.
    }

    Theme.init();
    AdminSidebar.initAll();
    Confirm.init();
    Message.init();
    ModalForm.init();
    await mountComponents(document);
}

function startAdmin() {
    void initializeAdmin().catch(function (error) {
        console.error("Lemonade Admin failed to initialize.", error);
        Message.show({ type: "error", key: "admin.message.error" });
    });
}

recoverFromStaleAssets();
window.addEventListener("pageshow", restoreEditorVersions);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
        restoreEditorVersions();
        startAdmin();
    });
} else {
    restoreEditorVersions();
    startAdmin();
}
