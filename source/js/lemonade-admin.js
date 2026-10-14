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
import { SessionUnlockHelper } from "./core/lemonade-session-unlock-helper.js";
import { Theme } from "./core/lemonade-theme.js";
import { mountComponents } from "./lemonade-admin-components.js";
import "./pages/lemonade-install.js";

class AdminSessionHeartbeat {
    static ACTIVITY_KEY = "lemonade-admin:session-activity-at";
    static HEARTBEAT_KEY = "lemonade-admin:session-heartbeat-at";
    static INTERVAL_MS = 120000;

    constructor(shell, lockManager) {
        this.endpoint = shell.getAttribute("data-lemonade-session-heartbeat-url") || "";
        this.lockManager = lockManager;
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
        if (this.lockManager.isLocked()) {
            return;
        }

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
            this.lockManager.confirmActivity(now);
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

class AdminSessionLockManager {
    static IDLE_MS = 1200000;
    static STATE_KEY = "lemonade-admin:session-state-change";

    constructor(shell) {
        this.shell = shell;
        this.statusUrl = shell.getAttribute("data-lemonade-session-status-url") || "";
        this.unlockUrl = shell.getAttribute("data-lemonade-session-unlock-url") || "";
        this.oidcUnlockUrl = shell.getAttribute("data-lemonade-session-oidc-unlock-url") || "";
        this.logoutUrl = shell.getAttribute("data-lemonade-logout-url") || "";
        this.loginUrl = shell.getAttribute("data-lemonade-admin-login-url") || "";
        this.lastConfirmedActivity = Date.now();
        this.locked = false;
        this.probing = null;
        this.oidc = false;
    }

    mount() {
        if (!this.statusUrl) return;
        window.addEventListener("lemonade:session:locked", () => void this.probe());
        window.addEventListener("storage", (event) => {
            if (event.key === AdminSessionLockManager.STATE_KEY) void this.probe();
        });
        window.addEventListener("pageshow", () => void this.probe());
        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) void this.probe();
        });
        document.addEventListener("click", (event) => this.interceptNavigation(event), true);
        document.addEventListener("submit", (event) => this.interceptSubmission(event), true);
        window.setInterval(() => {
            if (Date.now() >= this.lastConfirmedActivity + AdminSessionLockManager.IDLE_MS) void this.probe();
        }, 30000);
    }

    confirmActivity(timestamp = Date.now()) {
        this.lastConfirmedActivity = timestamp;
    }

    isLocked() {
        return this.locked;
    }

    async probe() {
        if (this.locked || this.probing) return this.probing;
        this.probing = window.fetch(this.statusUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" })
            .then(async (response) => {
                if (response.status === 423) {
                    const payload = await response.json().catch(() => null);
                    this.oidc = payload?.unlock === "oidc";
                    this.lock();
                }
                else if (response.status === 401) this.redirectToLogin();
                else if (response.ok) this.confirmActivity();
            })
            .finally(() => { this.probing = null; });
        return this.probing;
    }

    async interceptNavigation(event) {
        const link = event.target.closest("a[href]");
        if (!link || link.target || link.hasAttribute("download") || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (!this.needsGuard()) return;
        event.preventDefault();
        await this.probe();
        if (!this.locked) window.location.assign(link.href);
    }

    async interceptSubmission(event) {
        if (event.defaultPrevented || !this.needsGuard()) return;
        event.preventDefault();
        const form = event.target;
        await this.probe();
        if (!this.locked) form.submit();
    }

    needsGuard() {
        return this.locked || Date.now() >= this.lastConfirmedActivity + AdminSessionLockManager.IDLE_MS;
    }

    lock() {
        if (this.locked) return;
        this.locked = true;
        this.broadcast();
        this.shell.inert = true;
        const overlay = document.createElement("div");
        overlay.className = "lemonade-session-lock";
        overlay.setAttribute("role", "dialog");
        overlay.setAttribute("aria-modal", "true");
        const unlockMarkup = this.oidc
            ? `<button class="btn lemonade-session-lock__primary" type="button" data-lemonade-oidc-unlock><i class="bi bi-building"></i>${I18n.t("auth.session_lock.oidc")}</button>`
            : `<form class="lemonade-session-lock__form"><label for="lemonade-session-password">${I18n.t("auth.session_lock.password")}</label><div class="lemonade-session-lock__input"><i class="bi bi-key"></i><input id="lemonade-session-password" type="password" autocomplete="current-password" placeholder="${I18n.t("auth.session_lock.password")}" required></div><button class="lemonade-session-lock__primary" type="submit"><i class="bi bi-unlock"></i>${I18n.t("auth.session_lock.unlock")}</button></form>`;
        const identity = document.querySelector("[data-lemonade-session-identity]");
        const identityMarkup = identity ? `<div class="lemonade-session-lock__identity"><span class="lemonade-session-lock__avatar">${identity.dataset.initials}</span><strong>${identity.dataset.name}</strong></div>` : "";
        overlay.innerHTML = `<div class="lemonade-session-lock__card"><div class="lemonade-session-lock__emblem"><i class="bi bi-lock"></i></div><h1>${I18n.t("auth.session_lock.title")}</h1><p class="lemonade-session-lock__lead">${I18n.t(this.oidc ? "auth.session_lock.oidc_description" : "auth.session_lock.description")}</p>${identityMarkup}<p class="lemonade-session-lock__verify">${I18n.t("auth.session_lock.verify")}</p><p class="text-danger mt-2" hidden></p>${unlockMarkup}<div class="lemonade-session-lock__divider"><span>${I18n.t("auth.session_lock.or")}</span></div><div class="lemonade-session-lock__actions"><button class="lemonade-session-lock__logout" type="button"><i class="bi bi-box-arrow-right"></i>${I18n.t("auth.session_lock.logout")}</button></div><p class="lemonade-session-lock__safe"><i class="bi bi-shield-check"></i>${I18n.t("auth.session_lock.safe")}</p></div>`;
        document.body.append(overlay);
        const form = overlay.querySelector("form");
        const password = overlay.querySelector("input");
        const error = overlay.querySelector(".text-danger");
        overlay.querySelector(".lemonade-session-lock__logout").addEventListener("click", () => this.logout());
        overlay.querySelector("[data-lemonade-oidc-unlock]")?.addEventListener("click", () => this.openOidcPopup(overlay, error));
        form?.addEventListener("submit", async (event) => {
            event.preventDefault();
            try {
                await HttpHelper.post(this.unlockUrl, { password: password.value });
                overlay.remove(); this.shell.inert = false; this.locked = false; this.confirmActivity(); this.broadcast();
            } catch (reason) {
                error.hidden = false;
                error.textContent = reason.status === 429 ? I18n.t("auth.session_lock.throttled") : I18n.t("auth.session_lock.invalid");
                password.focus();
            }
        });
        (password || overlay.querySelector("[data-lemonade-oidc-unlock]"))?.focus();
    }

    openOidcPopup(overlay, error) {
        SessionUnlockHelper.openOidcPopup(this.oidcUnlockUrl, async () => {
            this.locked = false;
            await this.probe();
            if (!this.locked) { overlay.remove(); this.shell.inert = false; this.confirmActivity(); this.broadcast(); }
            else { error.hidden = false; error.textContent = I18n.t("auth.session_lock.oidc_failed"); }
        }, (reason) => { error.hidden = false; error.textContent = I18n.t(`auth.session_lock.${reason}`); });
    }

    logout() {
        const form = document.createElement("form");
        form.method = "post"; form.action = this.logoutUrl;
        const existingLogoutForm = Array.from(document.querySelectorAll("form")).find((candidate) => candidate.action === new URL(this.logoutUrl, window.location.origin).toString());
        const csrfField = existingLogoutForm?.querySelector("input[type=hidden]");
        if (csrfField) form.append(csrfField.cloneNode(true));
        document.body.append(form); form.submit();
    }

    broadcast() {
        try { window.localStorage.setItem(AdminSessionLockManager.STATE_KEY, String(Date.now())); } catch (error) { /* Cross-tab coordination is best effort. */ }
    }

    redirectToLogin() {
        if (!this.loginUrl) return;
        const destination = new URL(this.loginUrl, window.location.origin);
        destination.searchParams.set("session_expired", "1");
        window.location.assign(destination.toString());
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
        const sessionLock = new AdminSessionLockManager(adminShell);
        sessionLock.mount();
        new AdminSessionHeartbeat(adminShell, sessionLock).mount();
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
