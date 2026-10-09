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

export class MfaQr {
    static initAll() {
        document.querySelectorAll("[data-lemonade-mfa-qr]").forEach(function (canvas) {
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
}
