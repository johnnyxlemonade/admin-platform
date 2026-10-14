/*!
 * Lemonade Session Unlock Helper
 *
 * Coordinates the same-origin OIDC unlock popup confirmation.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
export class SessionUnlockHelper {
    static openOidcPopup(url, onSuccess, onError) {
        const popup = window.open(url, "lemonade-session-oidc-unlock", "popup,width=520,height=720");
        if (!popup) {
            onError("popup_blocked");
            return;
        }

        const receive = async (event) => {
            if (event.origin !== window.location.origin || event.data?.type !== "lemonade:session:oidc-unlock") {
                return;
            }

            window.removeEventListener("message", receive);
            if (!event.data.success) {
                onError("oidc_failed");
                return;
            }

            try {
                await onSuccess();
            } catch {
                onError("oidc_failed");
            }
        };
        window.addEventListener("message", receive);
    }

    static async isActive(statusUrl) {
        const response = await window.fetch(statusUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" });

        return response.ok;
    }
}
