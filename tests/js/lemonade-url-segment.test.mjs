import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = (await readFile(new URL("../../source/js/components/lemonade-url-segment.js", import.meta.url), "utf8"))
    .replace(/^import .*;\n/gm, "")
    .replace("export class UrlSegment", "const DomHelper = { queryAll: () => [] };\nexport class UrlSegment");
const { normalizeUrlSegment, UrlSegment } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

class FakeElement {
    constructor(attributes = {}) {
        this.attributes = new Map(Object.entries(attributes));
        this.listeners = new Map();
        this.value = "";
        this.maxLength = 180;
        this.form = null;
        this.root = null;
    }

    addEventListener(type, listener) {
        this.listeners.set(type, listener);
    }

    removeEventListener(type) {
        this.listeners.delete(type);
    }

    hasAttribute(name) {
        return this.attributes.has(name);
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    closest(selector) {
        return selector === "[data-lemonade-url-segment-root]" ? this.root : null;
    }
}

test("normalizes Czech diacritics and repeated separators", () => {
    assert.equal(normalizeUrlSegment(" Žluťoučký   kůň! "), "zlutoucky-kun");
});

test("normalizes pasted URL-like text to one segment", () => {
    assert.equal(normalizeUrlSegment("  Nová / nabídka  "), "nova-nabidka");
});

test("respects the field maximum length without a trailing separator", () => {
    assert.equal(normalizeUrlSegment("abc def", 5), "abc-d");
    assert.equal(normalizeUrlSegment("abc def", 4), "abc");
});

test("normalizes typing, change and paste on the shared field", () => {
    globalThis.window = { setTimeout: (callback) => callback() };
    const root = new FakeElement();
    const input = new FakeElement();
    input.root = root;
    input.value = " Žlutý kůň ";
    const component = new UrlSegment(input);

    input.value = " Ruční změna! ";
    component.handleInput();
    assert.equal(input.value, "rucni-zmena");

    input.value = " Změna hodnoty ";
    component.handleChange();
    assert.equal(input.value, "zmena-hodnoty");

    input.value = " Vložený / text ";
    component.handlePaste();
    assert.equal(input.value, "vlozeny-text");
});

test("new route follows the title only until the URL is edited manually", () => {
    globalThis.CSS = { escape: (value) => value };
    const root = new FakeElement();
    const sourceField = new FakeElement();
    sourceField.value = "První název";
    const input = new FakeElement({
        "data-lemonade-url-segment-auto": "",
        "data-lemonade-url-segment-source": "title"
    });
    input.root = root;
    input.form = { querySelector: () => sourceField };
    const component = new UrlSegment(input);

    assert.equal(input.value, "prvni-nazev");
    sourceField.value = "Druhý název";
    component.handleSourceInput();
    assert.equal(input.value, "druhy-nazev");

    input.value = "Vlastní URL";
    component.handleInput();
    sourceField.value = "Třetí název";
    component.handleSourceInput();
    assert.equal(input.value, "vlastni-url");
});
