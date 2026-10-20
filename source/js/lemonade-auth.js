/*!
 * Lemonade Auth
 *
 * Initializes Lemonade authentication UI.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { Clock } from "./components/lemonade-clock.js";
import { Dropdown } from "./components/lemonade-dropdown.js";
import { I18n } from "./core/lemonade-i18n.js";
import { SessionUnlockHelper } from "./core/lemonade-session-unlock-helper.js";
import { Message } from "./components/lemonade-message.js";
import { MfaQr } from "./components/lemonade-mfa-qr.js";
import { PasswordToggle } from "./components/lemonade-password-toggle.js";
import { Theme } from "./core/lemonade-theme.js";

function sessionUnlockRetryAfter(value) {
    const seconds = Number.parseInt(value, 10);

    return Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
}

function sessionUnlockThrottleMessage(seconds) {
    if (seconds < 60) {
        return I18n.t("auth.errors.throttled_seconds", { seconds: Math.max(1, seconds) });
    }

    return I18n.t("auth.errors.throttled_minutes", { minutes: Math.ceil(seconds / 60) });
}

    export class AuthLogin {
        constructor(form) {
            this.form = form;
            this.submit = form.querySelector('[type="submit"]');
            this.error = form.querySelector("[data-lemonade-auth-error]");
            this.errorCopy = form.querySelector("[data-lemonade-auth-error-copy]");
        }

        mount() {
            this.form.addEventListener("submit", (event) => {
                void this.submitForm(event);
            });
            this.error?.addEventListener("click", (event) => {
                if (event.target.closest("[data-lemonade-message-close]")) {
                    this.clearError();
                }
            });
        }

        async submitForm(event) {
            event.preventDefault();
            this.setSubmitting(true);
            this.clearError();

            try {
                const response = await window.fetch(this.form.action, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
                    },
                    body: new URLSearchParams(new FormData(this.form)).toString(),
                    credentials: "same-origin"
                });
                const payload = await this.payload(response);

                this.syncCsrf(response, payload);

                if (response.ok && payload.success === true && typeof payload.redirect === "string") {
                    window.location.assign(payload.redirect);
                    return;
                }

                this.showError(payload);
            } catch (error) {
                this.showError({ message: this.defaultErrorMessage() });
            }

            this.setSubmitting(false);
        }

        async payload(response) {
            const contentType = response.headers.get("Content-Type") || "";
            if (!contentType.includes("application/json")) {
                return {};
            }

            try {
                return await response.json();
            } catch (error) {
                return {};
            }
        }

        syncCsrf(response, payload) {
            const csrf = payload && payload.csrf;
            const token = csrf && typeof csrf.value === "string"
                ? csrf.value
                : response.headers.get("X-CSRF-Token");
            const name = csrf && typeof csrf.name === "string" ? csrf.name : "LEMONADE_CSRF";

            if (!token) {
                return;
            }

            const field = this.form.querySelector(`input[name="${name}"]`);
            if (field) {
                field.value = token;
            }

            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                meta.content = token;
            }
        }

        showError(payload) {
            const errors = payload && payload.errors;
            const message = payload && typeof payload.message === "string"
                ? payload.message
                : errors && typeof errors.login === "string"
                    ? errors.login
                    : this.defaultErrorMessage();

            if (!this.error || !this.errorCopy) {
                return;
            }

            this.errorCopy.textContent = message;
            this.error.hidden = false;
            this.form.querySelectorAll("[required]").forEach(function (field) {
                field.classList.add("is-invalid");
            });
        }

        clearError() {
            if (this.error) {
                this.error.hidden = true;
            }
            if (this.errorCopy) {
                this.errorCopy.textContent = "";
            }
            this.form.querySelectorAll("[required]").forEach(function (field) {
                field.classList.remove("is-invalid");
            });
        }

        defaultErrorMessage() {
            const key = this.form.dataset.lemonadeAuthDefaultErrorKey || "auth.errors.invalid";
            const message = I18n.t(key);

            return message === key
                ? this.form.dataset.lemonadeAuthDefaultError || ""
                : message;
        }

        setSubmitting(submitting) {
            if (!this.submit) {
                return;
            }

            this.submit.disabled = submitting;
            this.submit.setAttribute("aria-busy", submitting ? "true" : "false");
            this.form.setAttribute("aria-busy", submitting ? "true" : "false");
        }

        static initAll() {
            document.querySelectorAll("[data-lemonade-auth-login]").forEach(function (form) {
                if (form.dataset.lemonadeAuthLoginMounted === "true") {
                    return;
                }

                const login = new AuthLogin(form);
                login.mount();
                form.dataset.lemonadeAuthLoginMounted = "true";
            });
        }
}

async function initializeAuth() {
        try {
            const shell = document.querySelector("[data-lemonade-admin-shell]") || document.querySelector("[data-lemonade-install-shell]");
            await I18n.init(shell);
        } catch (error) {
            // HTML fallback text remains available when translation assets cannot be loaded.
        }
        Theme.init();
        Dropdown.mount(document);
        Message.init();
        Clock.initAll();
        PasswordToggle.initAll();
        MfaQr.initAll();
        AuthLogin.initAll();
        document.querySelectorAll("[data-lemonade-session-lock-screen]").forEach(function (screen) {
            const error = screen.querySelector("[data-lemonade-session-lock-error]");
            const errorCopy = error.querySelector("[data-lemonade-session-lock-error-copy]");
            const form = screen.querySelector("[data-lemonade-session-local-unlock]");
            const submit = form?.querySelector('[type="submit"]');
            let throttleUntil = 0;
            let throttleTimer = null;
            let submitting = false;
            const showError = (key) => { errorCopy.textContent = I18n.t(key); error.hidden = false; };
            const redirectAfterUnlock = () => window.location.assign(screen.dataset.lemonadeSessionReturnUrl || "/");
            const updateThrottle = () => {
                const remaining = Math.max(0, Math.ceil((throttleUntil - Date.now()) / 1000));
                if (remaining > 0) {
                    errorCopy.textContent = sessionUnlockThrottleMessage(remaining);
                    error.hidden = false;
                } else if (throttleUntil > 0) {
                    window.clearInterval(throttleTimer);
                    throttleTimer = null;
                    throttleUntil = 0;
                    error.hidden = true;
                    errorCopy.textContent = "";
                }
                if (submit) {
                    submit.disabled = submitting || remaining > 0;
                    submit.setAttribute("aria-disabled", submit.disabled ? "true" : "false");
                }
            };
            const startThrottle = (seconds) => {
                const retryAfter = sessionUnlockRetryAfter(seconds);
                if (retryAfter === 0) {
                    showError("auth.session_lock.throttled");
                    return;
                }
                throttleUntil = Date.now() + retryAfter * 1000;
                window.clearInterval(throttleTimer);
                updateThrottle();
                throttleTimer = window.setInterval(updateThrottle, 1000);
            };
            form?.addEventListener("submit", async (event) => {
                event.preventDefault();
                if (Date.now() < throttleUntil || submitting) {
                    updateThrottle();
                    return;
                }
                submitting = true;
                updateThrottle();
                error.hidden = true;
                try {
                    const response = await window.fetch(screen.dataset.lemonadeSessionUnlockUrl, { method: "POST", headers: { Accept: "application/json", "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]')?.content || "", "Content-Type": "application/json" }, body: JSON.stringify({ password: new FormData(event.currentTarget).get("password") }), credentials: "same-origin" });
                    if (response.ok) {
                        redirectAfterUnlock();
                        return;
                    }
                    if (response.status === 429) {
                        const payload = await response.json().catch(() => null);
                        startThrottle(response.headers.get("Retry-After") || payload?.retryAfterSeconds);
                    } else {
                        showError("auth.session_lock.invalid");
                    }
                } catch (reason) {
                    showError("auth.session_lock.invalid");
                } finally {
                    submitting = false;
                    updateThrottle();
                }
            });
            screen.querySelector("[data-lemonade-session-oidc-unlock]")?.addEventListener("click", () => {
                SessionUnlockHelper.openOidcPopup(screen.dataset.lemonadeSessionOidcUnlockUrl, async () => {
                    if (await SessionUnlockHelper.isActive(screen.dataset.lemonadeSessionStatusUrl)) redirectAfterUnlock(); else showError("auth.session_lock.oidc_failed");
                }, (reason) => showError(`auth.session_lock.${reason}`));
            });
            if (sessionUnlockRetryAfter(screen.dataset.lemonadeSessionRetryAfter) > 0) {
                startThrottle(screen.dataset.lemonadeSessionRetryAfter);
            }
        });
}

function startAuth() {
    void initializeAuth().catch(function (error) {
        console.error("Lemonade Auth failed to initialize.", error);
        Message.show({ type: "error", key: "admin.message.error" });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", startAuth);
} else {
    startAuth();
}
