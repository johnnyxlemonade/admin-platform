import assert from "node:assert/strict";
import test from "node:test";

globalThis.document = { readyState: "loading", addEventListener() {} };

const { Action } = await import("../../source/js/components/lemonade-action.js");

test("successful save applies only persisted editor fields", () => {
    const controls = {
        publication_state: { value: "2" },
        published_at: { value: "" },
        title: { value: "Unsent draft" },
    };
    const form = {
        hasAttribute: (name) => name === "data-lemonade-editor-form",
        elements: { namedItem: (name) => controls[name] || null },
    };
    const action = new Action({});

    action.updateEditorFields({ data: { editorFields: {
        publication_state: "2", published_at: "2026-10-27T12:34",
    } } }, form);
    assert.equal(controls.published_at.value, "2026-10-27T12:34");
    assert.equal(controls.title.value, "Unsent draft");

    action.updateEditorFields({ data: { editorFields: {
        publication_state: "1", published_at: "",
    } } }, form);
    assert.equal(controls.publication_state.value, "1");
    assert.equal(controls.published_at.value, "");
});
