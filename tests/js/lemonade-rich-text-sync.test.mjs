import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

class FakeHtmlFragment {
    set innerHTML(value) {
        this.textContent = value.replace(/<[^>]*>/g, "");
    }

    querySelector() {
        return null;
    }
}

globalThis.document = {
    createElement() { return new FakeHtmlFragment(); },
    addEventListener() {},
    removeEventListener() {},
};
globalThis.Event = class {
    constructor(type, options = {}) {
        this.type = type;
        this.bubbles = options.bubbles === true;
    }
};

const source = [
    "const $ = () => ({ off() {}, data() { return null; } });",
    (await readFile(new URL("../../source/js/components/lemonade-rich-text.js", import.meta.url), "utf8"))
        .replace(/^import .*;\n/gm, "")
        .replace("class RichText", "export class RichText"),
].join("\n");
const { RichText } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

class FakeTextarea {
    constructor(value) {
        this.value = value;
        this.events = [];
    }

    closest() {
        return null;
    }

    dispatchEvent(event) {
        this.events.push(event.type);
        return true;
    }
}

function richText(value = "<p>Initial</p>") {
    const textarea = new FakeTextarea(value);
    const component = new RichText(textarea);
    component.canonicalValue = value;

    return { component, textarea };
}

function viewHtmlButtonEvent() {
    return { target: { closest() { return {}; } } };
}

test("viewHTML toggles do not emit dirty-state events", () => {
    const { component, textarea } = richText();

    component.handleViewHtmlToggle(viewHtmlButtonEvent());
    textarea.value = "<p>Initial</p>\n";
    component.handleChange();
    component.handleViewHtmlToggle(viewHtmlButtonEvent());
    textarea.value = "<p>Initial</p>";
    component.handleChange();

    assert.deepEqual(textarea.events, []);
    assert.equal(textarea.value, "<p>Initial</p>");
});

test("a WYSIWYG change emits canonical input and change", () => {
    const { component, textarea } = richText();
    textarea.value = "<p>Changed</p>";

    component.handleChange();

    assert.deepEqual(textarea.events, ["input", "change"]);
});

test("a source edit emits canonical change and can return to its initial value", () => {
    const { component, textarea } = richText();
    textarea.value = "<p>Changed</p>";
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
    component.handleTextareaInput();
    textarea.value = "<p>Initial</p>";
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
    component.handleTextareaInput();

    assert.deepEqual(textarea.events, ["input", "change", "input", "change"]);
    assert.equal(component.canonicalValue, "<p>Initial</p>");
    assert.equal(textarea.value, "<p>Initial</p>");
});

test("a locale change remounts the editor without changing its canonical value", async () => {
    const { component, textarea } = richText();
    let locale = "en";
    textarea.closest = function () {
        return { getAttribute() { return locale; } };
    };
    component.locale = "cs";
    let destroyed = false;
    let mounted = false;
    component.destroy = function () { destroyed = true; };
    component.mount = async function () { mounted = true; };

    await component.handleLocaleChange();

    assert.equal(destroyed, true);
    assert.equal(mounted, true);
    assert.equal(textarea.value, "<p>Initial</p>");
    assert.deepEqual(textarea.events, []);
});
