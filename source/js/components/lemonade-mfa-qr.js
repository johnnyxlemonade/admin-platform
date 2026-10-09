/*!
 * Lemonade MFA QR
 *
 * Renders locally generated QR codes for TOTP provisioning URIs.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import QRCode from "qrcode";
import { EventHelper } from "../core/lemonade-event-helper.js";

export class MfaQr {
    static initAll(root = document) {
        const canvases = root.matches?.("[data-lemonade-mfa-qr]")
            ? [root]
            : Array.from(root.querySelectorAll("[data-lemonade-mfa-qr]"));

        canvases.forEach(function (canvas) {
            const provisioningUri = canvas.dataset.provisioningUri || "";

            if (!provisioningUri || canvas.dataset.lemonadeMfaQrMounted === "true") {
                return;
            }

            QRCode.toCanvas(canvas, provisioningUri, {
                errorCorrectionLevel: "M",
                margin: 2,
                width: 224,
                color: { dark: "#10192b", light: "#ffffff" }
            }).then(function () {
                canvas.dataset.lemonadeMfaQrMounted = "true";
            }).catch(function () {
                canvas.hidden = true;
            });
        });
    }

    static mount(root) {
        this.initAll(root);
        this.bindCopy(root);
    }

    static bindCopy(root) {
        if (root.lemonadeMfaQrCopyCleanup) {
            return;
        }

        root.lemonadeMfaQrCopyCleanup = EventHelper.delegate(root, "click", "[data-lemonade-mfa-copy]", (event, trigger) => {
            event.preventDefault();
            const targetId = trigger.getAttribute("data-lemonade-mfa-copy-target") || "";
            const target = Array.from(root.querySelectorAll("input")).find((input) => input.id === targetId);
            if (!target) {
                return;
            }

            void this.copy(target);
        });
    }

    static async copy(input) {
        try {
            await window.navigator.clipboard.writeText(input.value);
        } catch {
            input.select();
            document.execCommand("copy");
            input.setSelectionRange(0, 0);
        }
    }

    static destroy(root) {
        root.lemonadeMfaQrCopyCleanup?.();
        delete root.lemonadeMfaQrCopyCleanup;
    }
}

export function mount(root = document) {
    MfaQr.mount(root);
}

export function destroy(root = document) {
    MfaQr.destroy(root);
}
