/*!
 * Lemonade Install
 *
 * Adds progressive enhancement to the shared-hosting installer.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */
import { BootstrapIcons } from "../core/lemonade-bootstrap-icons.js";
import { HttpHelper } from "../core/lemonade-http-helper.js";
import { I18n } from "../core/lemonade-i18n.js";
import { Message } from "../components/lemonade-message.js";

    function boot() {
        const root = document.querySelector("[data-lemonade-install]");
        if (!root) {
            return;
        }

        const form = root ? root.querySelector("[data-install-progressive]") : null;
        if (!root || root.getAttribute("data-install-preflight-ready") !== "1" || !form) {
            return;
        }

        const endpoint = root.getAttribute("data-install-endpoint");
        const initial = root.getAttribute("data-install-initial") === "1";
        const steps = ["database", "modules", "permissions"];
        if (initial) {
            steps.push("root");
        }
        let authorizationSent = false;

        const translated = function (key) {
            if (typeof I18n.t !== "function") {
                return "";
            }

            const message = I18n.t(key);

            return message === key ? "" : message;
        };

        const isTranslationKey = function (value) {
            return typeof value === "string" && /^[A-Za-z][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)+$/.test(value);
        };

        const requestError = function () {
            return translated("install.installErrors.operation_failed");
        };

        const field = function (name) {
            return form.querySelector('[name="' + name + '"]');
        };

        const stepElement = function (step) {
            return root.querySelector('[data-install-step="' + step + '"]');
        };

        const updateProgress = function () {
            const progress = root.querySelector("[data-install-progress]");
            const progressBar = root.querySelector("[data-install-progress-bar]");
            const items = root.querySelectorAll("[data-install-step]");
            if (!progress || !progressBar || items.length === 0) {
                return;
            }

            let completed = 0;
            Array.prototype.forEach.call(items, function (item) {
                const status = item.getAttribute("data-install-status");
                if (status === "success" || status === "skipped") {
                    completed += 1;
                }
            });
            const percentage = Math.round((completed / items.length) * 100);
            progressBar.style.width = percentage + "%";
            progress.setAttribute("aria-valuenow", String(percentage));

            const label = root.querySelector("[data-install-progress-label]");
            const template = translated("install.installPresentation.progress_completed");
            if (label && template) {
                label.textContent = template
                    .replace("{completed}", String(completed))
                    .replace("{total}", String(items.length));
            }
        };

        const setStep = function (step, status, message) {
            const item = stepElement(step);
            if (!item) {
                return;
            }

            item.setAttribute("data-install-status", status);
            if (status === "running") {
                item.setAttribute("aria-current", "step");
            } else {
                item.removeAttribute("aria-current");
            }
            const statusNode = item.querySelector("[data-install-step-status]");
            const messageNode = item.querySelector("[data-install-step-message]");
            const icon = item.querySelector("[data-install-step-icon]");
            const statusLabel = translated("install.installSteps." + status);
            if (statusNode) {
                statusNode.setAttribute("data-lemonade-i18n", "install.installSteps." + status);
                statusNode.textContent = statusLabel;
            }
            if (messageNode) {
                messageNode.textContent = message || "";
                messageNode.setAttribute("role", status === "error" ? "alert" : "status");
            }
            if (icon) {
                const iconByStatus = {
                    pending: "circle",
                    running: "arrow-repeat",
                    success: "check-circle",
                    error: "x-circle",
                    skipped: "circle"
                };
                BootstrapIcons.set(icon, iconByStatus[status] || "circle");
                icon.classList.toggle("spinner-border", status === "running");
                icon.classList.toggle("spinner-border-sm", status === "running");
                icon.setAttribute("aria-label", status === "running" ? statusLabel : "");
            }
            if (statusNode) {
                ["pending", "running", "success", "error", "skipped"].forEach(function (badgeStatus) {
                    statusNode.classList.remove("lemonade-install-badge--" + badgeStatus);
                });
                statusNode.classList.add("lemonade-install-badge--" + status);
            }
            updateProgress();
        };

        const setBusy = function (busy) {
            Array.prototype.forEach.call(form.elements, function (element) {
                element.disabled = busy;
            });
            const submit = form.querySelector("[data-install-submit]");
            const spinner = form.querySelector("[data-install-submit-spinner]");
            if (submit) {
                submit.setAttribute("aria-busy", String(busy));
            }
            if (spinner) {
                spinner.hidden = !busy;
                spinner.setAttribute("aria-hidden", String(!busy));
            }
        };

        const updateCsrf = function (payload) {
            if (!payload || typeof payload.csrfToken !== "string" || payload.csrfToken === "") {
                return;
            }
            const token = document.querySelector('meta[name="csrf-token"]');
            if (token) {
                token.content = payload.csrfToken;
            }
        };

        const errorMessage = function (error) {
            if (error && error.response && typeof error.response.message === "string" && error.response.message !== "") {
                return error.response.message;
            }
            return "";
        };

        const runStep = async function (step) {
            const existing = stepElement(step);
            if (existing && ["success", "skipped"].includes(existing.getAttribute("data-install-status"))) {
                return true;
            }
            const payload = { step: step };
            if (initial && !authorizationSent) {
                const installKey = field("install_key");
                if (installKey && installKey.value !== "") {
                    payload.install_key = installKey.value;
                }
            }
            if (step === "root" && initial) {
                payload.email = field("email") ? field("email").value : "";
                payload.password = field("password") ? field("password").value : "";
            }

            setStep(step, "running", "");
            try {
                const response = await HttpHelper.post(endpoint, payload);
                updateCsrf(response);
                if (!response || response.step !== step || typeof response.status !== "string") {
                    throw new Error("Invalid installer response.");
                }
                const responseMessage = typeof response.message === "string" ? response.message : "";
                setStep(step, response.status, isTranslationKey(responseMessage) ? requestError() : responseMessage);
                if (!response.success || response.status === "error") {
                    return false;
                }
                if (initial && !authorizationSent) {
                    const installKey = field("install_key");
                    if (installKey) {
                        installKey.value = "";
                    }
                    authorizationSent = true;
                }
                if (step === "root") {
                    const password = field("password");
                    if (password) {
                        password.value = "";
                    }
                    window.location.assign(root.getAttribute("data-install-login-url"));
                    return true;
                }
                return true;
            } catch (error) {
                const message = errorMessage(error);
                const safeMessage = message && !isTranslationKey(message) ? message : requestError();
                setStep(step, "error", safeMessage);
                if (Message && typeof Message.error === "function") {
                    Message.error(requestError() || root.getAttribute("data-install-generic-error") || "");
                }
                return false;
            }
        };

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            setBusy(true);
            const submitLabel = form.querySelector("[data-install-submit-label]");
            if (submitLabel) {
                submitLabel.textContent = translated("install.install.actions." + (initial ? "install" : "update"));
            }
            void (async function () {
                for (let index = 0; index < steps.length; index += 1) {
                    if (!(await runStep(steps[index]))) {
                        setBusy(false);
                        if (submitLabel) {
                            submitLabel.textContent = translated("install.install.actions.retry");
                        }
                        return;
                    }
                }
                setBusy(false);
                if (!initial) {
                    window.location.assign(root.getAttribute("data-install-login-url"));
                }
            })().catch(function () {
                setBusy(false);
                if (submitLabel) {
                    submitLabel.textContent = translated("install.install.actions.retry");
                }
                if (Message && typeof Message.error === "function") {
                    Message.error(requestError() || root.getAttribute("data-install-generic-error") || "");
                }
            });
        });

        updateProgress();
        document.addEventListener("lemonade:locale:changed", function () {
            updateProgress();
            Array.prototype.forEach.call(root.querySelectorAll("[data-install-step]"), function (item) {
                const status = item.getAttribute("data-install-status");
                const statusNode = item.querySelector("[data-install-step-status]");
                const statusLabel = translated("install.installSteps." + status);
                if (statusNode && statusLabel) {
                    statusNode.setAttribute("data-lemonade-i18n", "install.installSteps." + status);
                    statusNode.textContent = statusLabel;
                }
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
