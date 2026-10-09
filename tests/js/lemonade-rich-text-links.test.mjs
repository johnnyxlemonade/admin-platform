import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

globalThis.document = {
    addEventListener() {},
    removeEventListener() {},
};

const source = [
    "const $ = () => ({ off() {}, data() { return null; } });",
    (await readFile(new URL("../../source/js/components/lemonade-rich-text.js", import.meta.url), "utf8"))
        .replace(/^import .*;\n/gm, "")
        .replace("class RichText", "export class RichText"),
].join("\n");
const { RichText } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

class FakeEditorElement {
    constructor() {
        this.listeners = new Map();
        this.link = {};
    }

    addEventListener(name, listener, capture) {
        this.listeners.set(name, { listener, capture });
    }

    removeEventListener(name, listener, capture) {
        const registered = this.listeners.get(name);
        if (registered?.listener === listener && registered.capture === capture) {
            this.listeners.delete(name);
        }
    }

    contains(element) {
        return element === this.link;
    }
}

function componentWithEditor() {
    const component = new RichText({
        closest() { return null; },
        addEventListener() {},
        removeEventListener() {},
    });
    const editor = new FakeEditorElement();
    component.editorElement = editor;
    component.editor = { off() {} };
    editor.addEventListener("click", component.handleLinkClick, true);

    return { component, editor };
}

function linkClick(editor, options = {}) {
    let prevented = false;
    editor.listeners.get("click").listener({
        ctrlKey: false,
        metaKey: false,
        target: { closest() { return editor.link; } },
        preventDefault() { prevented = true; },
        ...options,
    });

    return prevented;
}

test("a plain primary editor link click does not navigate", () => {
    const { editor } = componentWithEditor();

    assert.equal(linkClick(editor), true);
});

test("a plain editor link click on a text node does not navigate", () => {
    const { editor } = componentWithEditor();

    assert.equal(linkClick(editor, {
        target: { parentElement: { closest() { return editor.link; } } },
    }), true);
});

test("a Ctrl editor link click preserves native preview navigation", () => {
    const { editor } = componentWithEditor();

    assert.equal(linkClick(editor, { ctrlKey: true }), false);
});

test("a Cmd editor link click preserves native preview navigation", () => {
    const { editor } = componentWithEditor();

    assert.equal(linkClick(editor, { metaKey: true }), false);
});

test("middle click remains native because the editor does not listen for auxclick", () => {
    const { editor } = componentWithEditor();

    assert.equal(editor.listeners.has("auxclick"), false);
});

test("destroy removes the editor link listener", () => {
    const { component, editor } = componentWithEditor();

    component.destroy();

    assert.equal(editor.listeners.has("click"), false);
});
