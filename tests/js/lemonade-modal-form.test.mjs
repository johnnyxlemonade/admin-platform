import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

class FakeContent {
    constructor() {
        this.submitListener = null;
    }

    addEventListener(event, listener) {
        if (event === "submit") {
            this.submitListener = listener;
        }
    }

    removeEventListener(event, listener) {
        if (event === "submit" && this.submitListener === listener) {
            this.submitListener = null;
        }
    }

    contains() {
        return true;
    }
}

globalThis.modalFormRequests = [];
globalThis.modalFormMounts = [];
const source = [
    `const DomHelper = { queryAll() { return []; } };
     const EventHelper = {
        delegate(element, event, selector, callback) {
            const listener = (submitted) => {
                const form = submitted.target.closest(selector);
                if (form) callback(submitted, form);
            };
            element.addEventListener(event, listener);
            return () => element.removeEventListener(event, listener);
        },
        on() { return () => {}; }
     };
     class HttpError extends Error {}
     const HttpHelper = {
        async request(url, options) {
            globalThis.modalFormRequests.push({ url, options });
            return { success: true, modalHtml: '<section>next step</section>' };
        }
     };
     const I18n = { async apply() {} };
     const Message = null;
     const Modal = {};
     async function destroyComponents() {}
     async function mountComponents(root) { globalThis.modalFormMounts.push(root); };
    `,
    (await readFile(new URL("../../source/js/components/lemonade-modal-form.js", import.meta.url), "utf8"))
        .replace(/^import .*;\n/gm, "")
].join("\n");

globalThis.window = {
    FormData: class {
        constructor(form) {
            this.form = form;
        }
    },
    location: { href: "https://portal.ddev.site/admin/system/users/edit/1" },
};

const { ModalForm } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

function form(action) {
    const attributes = new Set();

    return {
        action,
        method: "post",
        closest(selector) {
            return selector === "form[data-lemonade-modal-form]" ? this : null;
        },
        hasAttribute(name) {
            return attributes.has(name);
        },
        setAttribute(name) {
            attributes.add(name);
        },
        removeAttribute(name) {
            attributes.delete(name);
        },
    };
}

async function submit(content, submittedForm) {
    let prevented = false;
    content.submitListener({
        target: submittedForm,
        preventDefault() {
            prevented = true;
        },
    });
    for (let step = 0; step < 4; step++) {
        await new Promise((resolve) => setImmediate(resolve));
    }

    return prevented;
}

function prepareModal() {
    const content = new FakeContent();
    const html = [];
    ModalForm.content = content;
    ModalForm.modal = { setContent(value) { html.push(value); } };
    ModalForm.formSubmitCleanup = null;
    ModalForm.initializeCountryFlagPreviews = () => {};
    ModalForm.showError = (response) => { ModalForm.lastError = response; };
    ModalForm.lastError = null;
    globalThis.modalFormRequests = [];
    globalThis.modalFormMounts = [];

    return { content, html };
}

test("modal enrollment start prevents navigation, posts through modal transport and replaces its content", async () => {
    const { content, html } = prepareModal();
    const originalLocation = window.location.href;
    await ModalForm.initializeContent();
    assert.equal(typeof content.submitListener, "function");

    const prevented = await submit(content, form("/admin/mfa/modal/enroll/start"));

    assert.equal(prevented, true);
    assert.equal(ModalForm.lastError, null);
    assert.equal(globalThis.modalFormRequests.length, 1);
    assert.equal(globalThis.modalFormMounts.length, 2);
    assert.equal(globalThis.modalFormMounts.at(-1), content);
    assert.deepEqual(globalThis.modalFormRequests.pop().url, "/admin/mfa/modal/enroll/start");
    assert.deepEqual(html, ["<section>next step</section>"]);
    assert.equal(window.location.href, originalLocation);
});

test("modal enrollment confirmation uses the rebound transport after enrollment replaces the content", async () => {
    const { content, html } = prepareModal();
    const originalLocation = window.location.href;
    await ModalForm.initializeContent();
    assert.equal(typeof content.submitListener, "function");

    await submit(content, form("/admin/mfa/modal/enroll/start"));
    const prevented = await submit(content, form("/admin/mfa/modal/enroll/confirm"));

    assert.equal(prevented, true);
    assert.equal(ModalForm.lastError, null);
    assert.equal(globalThis.modalFormRequests.length, 2);
    assert.deepEqual(globalThis.modalFormRequests.pop().url, "/admin/mfa/modal/enroll/confirm");
    assert.deepEqual(html, ["<section>next step</section>", "<section>next step</section>"]);
    assert.equal(window.location.href, originalLocation);
});
