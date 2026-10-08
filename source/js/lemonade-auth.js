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
import { Message } from "./components/lemonade-message.js";
import { PasswordToggle } from "./components/lemonade-password-toggle.js";
import { Theme } from "./core/lemonade-theme.js";
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
            this.form.querySelectorAll('[name="email"], [name="password"]').forEach(function (field) {
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
            this.form.querySelectorAll('[name="email"], [name="password"]').forEach(function (field) {
                field.classList.remove("is-invalid");
            });
        }

        defaultErrorMessage() {
            const message = I18n.t("auth.errors.invalid");

            return message === "auth.errors.invalid"
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
        AuthLogin.initAll();
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
