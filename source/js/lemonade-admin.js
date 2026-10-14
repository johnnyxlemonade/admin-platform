/*!
 * Lemonade Admin
 *
 * Initializes the eager Admin runtime without changing its DOM lifecycle.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { AdminSidebar } from "./components/lemonade-admin-sidebar.js";
import { Confirm } from "./components/lemonade-confirm.js";
import { Message } from "./components/lemonade-message.js";
import { HttpHelper } from "./core/lemonade-http-helper.js";
import { ModalForm } from "./components/lemonade-modal-form.js";
import { I18n } from "./core/lemonade-i18n.js";
import { Theme } from "./core/lemonade-theme.js";
import { mountComponents } from "./lemonade-admin-components.js";
import "./pages/lemonade-install.js";

class AdminSessionHeartbeat {
    static ACTIVITY_KEY = "lemonade-admin:session-activity-at";
    static HEARTBEAT_KEY = "lemonade-admin:session-heartbeat-at";
    static INTERVAL_MS = 120000;

    constructor(shell) {
        this.endpoint = shell.getAttribute("data-lemonade-session-heartbeat-url") || "";
        this.timer = null;
        this.activityAt = 0;
        this.lastHeartbeatAt = 0;
        this.recordActivity = this.recordActivity.bind(this);
    }

    mount() {
        if (!this.endpoint) {
            return;
        }

        ["pointerdown", "mousedown", "keydown", "touchstart"].forEach((eventName) => {
            window.addEventListener(eventName, this.recordActivity, { passive: true });
        });
        this.timer = window.setInterval(() => {
            void this.sendHeartbeatWhenNeeded();
        }, AdminSessionHeartbeat.INTERVAL_MS);
    }

    recordActivity() {
        this.writeTimestamp(AdminSessionHeartbeat.ACTIVITY_KEY, Date.now());
    }

    async sendHeartbeatWhenNeeded() {
        const activityAt = this.readTimestamp(AdminSessionHeartbeat.ACTIVITY_KEY);
        const lastHeartbeatAt = this.readTimestamp(AdminSessionHeartbeat.HEARTBEAT_KEY);
        const now = Date.now();
        if (activityAt <= lastHeartbeatAt || now - lastHeartbeatAt < AdminSessionHeartbeat.INTERVAL_MS) {
            return;
        }

        this.writeTimestamp(AdminSessionHeartbeat.HEARTBEAT_KEY, now);
        if (this.readTimestamp(AdminSessionHeartbeat.HEARTBEAT_KEY) !== now) {
            return;
        }

        try {
            await HttpHelper.post(this.endpoint);
        } catch (error) {
            if (this.readTimestamp(AdminSessionHeartbeat.HEARTBEAT_KEY) === now) {
                this.writeTimestamp(AdminSessionHeartbeat.HEARTBEAT_KEY, lastHeartbeatAt);
            }
        }
    }

    readTimestamp(key) {
        try {
            const value = Number(window.localStorage.getItem(key));

            return Number.isFinite(value) && value > 0 ? value : 0;
        } catch (error) {
            return key === AdminSessionHeartbeat.ACTIVITY_KEY ? this.activityAt : this.lastHeartbeatAt;
        }
    }

    writeTimestamp(key, timestamp) {
        if (key === AdminSessionHeartbeat.ACTIVITY_KEY) {
            this.activityAt = timestamp;
        } else {
            this.lastHeartbeatAt = timestamp;
        }

        try {
            window.localStorage.setItem(key, String(timestamp));
        } catch (error) {
            // In-memory timestamps retain the current-tab heartbeat when storage is unavailable.
        }
    }
}

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
    const adminShell = document.querySelector("[data-lemonade-admin-shell]");
    if (adminShell) {
        new AdminSessionHeartbeat(adminShell).mount();
    }
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
