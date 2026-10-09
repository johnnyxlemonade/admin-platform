import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

globalThis.mfaQrCalls = [];
const source = [
    `const QRCode = {
        toCanvas(canvas, uri, options) {
            globalThis.mfaQrCalls.push({ canvas, uri, options });
            return Promise.resolve();
        }
    };
    const EventHelper = { delegate() { return () => {}; } };`,
    (await readFile(new URL("../../source/js/components/lemonade-mfa-qr.js", import.meta.url), "utf8"))
        .replace('import QRCode from "qrcode";\n', "")
        .replace('import { EventHelper } from "../core/lemonade-event-helper.js";\n', "")
].join("\n");

const { mount } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

test("mount renders a QR code from the provisioning URI in dynamically inserted modal content", async () => {
    const canvas = {
        dataset: { provisioningUri: "otpauth://totp/Portal:root?secret=ABC123" },
        hidden: false,
    };
    const modalContent = {
        matches() { return false; },
        querySelectorAll(selector) {
            return selector === "[data-lemonade-mfa-qr]" ? [canvas] : [];
        },
    };

    mount(modalContent);
    await Promise.resolve();

    assert.equal(globalThis.mfaQrCalls.length, 1);
    assert.equal(globalThis.mfaQrCalls[0].canvas, canvas);
    assert.equal(globalThis.mfaQrCalls[0].uri, "otpauth://totp/Portal:root?secret=ABC123");
    assert.equal(canvas.dataset.lemonadeMfaQrMounted, "true");
});
