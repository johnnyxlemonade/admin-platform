/*!
 * Lemonade HTTP Helper
 *
 * Handles HTTP requests and normalized errors.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
export class HttpError extends Error {
        constructor(message, status, response, headers) {
            super(message);
            this.name = "LemonadeHttpError";
            this.status = status;
            this.response = response;
            this.headers = headers || null;
        }
}

export class HttpHelper {
        static sessionExpiryRedirecting = false;

        static get(url, options) { return this.request(url, Object.assign({}, options, { method: "GET" })); }
        static post(url, data, options) { return this.request(url, Object.assign({}, options, { method: "POST", data: data })); }
        static patch(url, data, options) { return this.request(url, Object.assign({}, options, { method: "PATCH", data: data })); }
        static delete(url, data, options) { return this.request(url, Object.assign({}, options, { method: "DELETE", data: data })); }

        static async request(url, options) {
            const settings = options || {};
            const headers = Object.assign({ Accept: "application/json" }, settings.headers || {});
            const requestOptions = { method: settings.method || "GET", headers: headers, signal: settings.signal, keepalive: settings.keepalive === true };

            if (!["GET", "HEAD", "OPTIONS"].includes(requestOptions.method.toUpperCase())) {
                const token = document.querySelector('meta[name="csrf-token"]');
                if (token && token.content) {
                    headers["X-CSRF-Token"] = token.content;
                }
            }

            if (settings.rawBody !== undefined && requestOptions.method !== "GET") {
                requestOptions.body = settings.rawBody;
            } else if (settings.data !== undefined && requestOptions.method !== "GET") {
                if (settings.data instanceof window.FormData) {
                    requestOptions.body = settings.data;
                } else {
                    headers["Content-Type"] = "application/json";
                    requestOptions.body = JSON.stringify(settings.data);
                }
            }

            const response = await window.fetch(url, requestOptions);
            const token = response.headers.get("X-CSRF-Token");
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (token && meta) {
                meta.content = token;
            }
            const contentType = response.headers.get("content-type") || "";
            const payload = contentType.indexOf("application/json") !== -1 ? await response.json() : await response.text();

            if (!response.ok) {
                this.redirectForUnauthenticatedResponse(response, payload);
                if (response.status === 423 && payload?.error?.code === "session_locked") {
                    window.dispatchEvent(new CustomEvent("lemonade:session:locked"));
                }
                throw new HttpError("HTTP request failed.", response.status, payload, response.headers);
            }

            return payload;
        }

        static redirectForUnauthenticatedResponse(response, payload) {
            if (response.status !== 401 || payload?.error?.code !== "unauthenticated" || this.sessionExpiryRedirecting) {
                return;
            }

            const shell = document.querySelector("[data-lemonade-admin-shell]");
            const loginUrl = shell?.getAttribute("data-lemonade-admin-login-url");
            if (!loginUrl) {
                return;
            }

            const destination = new URL(loginUrl, window.location.origin);
            if (destination.pathname === window.location.pathname) {
                return;
            }

            this.sessionExpiryRedirecting = true;
            destination.searchParams.set("session_expired", "1");
            window.location.assign(destination.toString());
        }
}
