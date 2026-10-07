import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

class FakeElement {
    constructor(tagName) {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.attributes = new Map();
        this.classList = { toggle() {}, add() {}, contains() { return false; } };
        this.listeners = new Map();
        this.hidden = false;
        this.textContent = "";
    }

    appendChild(child) {
        this.children.push(child);
        child.parentNode = this;
        return child;
    }

    replaceChildren(...children) {
        this.children = [];
        children.forEach((child) => this.appendChild(child));
    }

    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.get(name) || null; }
    hasAttribute(name) { return this.attributes.has(name); }
    addEventListener(name, listener) { this.listeners.set(name, listener); }
    dispatchEvent(event) {
        this.listeners.get(event.type)?.(event);
        return true;
    }

    click() { this.dispatchEvent({ type: "click" }); }

    querySelector(selector) {
        const attribute = selector.match(/^\[([^\]]+)\]$/)?.[1];
        return this.children.find((child) => attribute && child.hasAttribute(attribute)) || null;
    }
}

class FakeOption extends FakeElement {
    constructor(text, value, _defaultSelected, selected) {
        super("option");
        this.text = text;
        this.value = value;
        this.selected = selected;
    }
}

class FakeSelect extends FakeElement {
    constructor(options = []) {
        super("select");
        this.multiple = true;
        this.disabled = false;
        this.changeCount = 0;
        options.forEach((option) => this.appendChild(option));
    }

    get options() { return this.children; }
    dispatchEvent(event) {
        if (event.type === "change") {
            this.changeCount += 1;
        }
        return super.dispatchEvent(event);
    }
}

globalThis.document = { createElement: (tagName) => new FakeElement(tagName) };
globalThis.Option = FakeOption;
globalThis.Event = class { constructor(type) { this.type = type; } };

const source = (await readFile(new URL("../../source/js/components/lemonade-select.js", import.meta.url), "utf8"))
    .replace(/^import .*;\n/gm, "")
    .replace(
        "export class Select",
        "const BootstrapIcons = { className: () => 'icon' };\nconst DomHelper = { queryAll: () => [] };\nconst EventHelper = { debounce: (callback) => callback, on: () => () => {}, animationFrame: () => ({ cancel() {} }) };\nconst HttpHelper = {};\nconst I18n = { t: (key, params = {}) => key.replace('{name}', params.name || '{name}') };\nexport class Select",
    );
const { Select } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

function option(value, selected = false) {
    return new FakeOption(value, value, false, selected);
}

function selectFor(options = []) {
    const select = Object.create(Select.prototype);
    select.element = new FakeSelect(options);
    select.allowCreate = true;
    select.createLabelKey = "admin.select.create";
    select.noResultsKey = "admin.select.no_results";
    select.localOptions = options.map((item) => ({ value: item.value, label: item.text }));
    select.resultOptions = select.localOptions;
    select.sourceType = "static";
    select.stateKey = "";
    select.query = "";
    select.searchInput = new FakeElement("input");
    select.results = new FakeElement("div");
    select.more = new FakeElement("button");
    select.trigger = new FakeElement("button");
    select.root = new FakeElement("div");
    select.positionDropdown = () => {};

    return select;
}

function createRow(select) {
    return select.results.children.find((item) => item.className === "lm-select-option lm-select-option-create") || null;
}

function emptyRow(select) {
    return select.results.children.find((item) => item.className === "lm-select-state") || null;
}

test("zero results render only the creatable row for a valid candidate", () => {
    const select = selectFor();
    select.query = "test";
    select.filterLocalOptions("test");

    assert.ok(createRow(select));
    assert.equal(emptyRow(select), null);
});

test("partial results retain the creatable row without an exact match", () => {
    const select = selectFor([option("Testovací článek")]);
    select.query = "Test";
    select.filterLocalOptions("Test");

    assert.equal(select.results.children.filter((item) => item.hasAttribute("data-lemonade-select-option")).length, 1);
    assert.ok(createRow(select));
    assert.equal(emptyRow(select), null);
});

test("an exact normalized option suppresses creation", () => {
    const exactMatch = selectFor([option("Tést")]);
    exactMatch.query = "test";
    exactMatch.filterLocalOptions("test");
    assert.equal(createRow(exactMatch), null);
});

test("an already selected normalized value suppresses creation", () => {
    const selectedMatch = selectFor([option("Test", true)]);
    selectedMatch.query = "test";
    selectedMatch.filterLocalOptions("test");
    assert.equal(createRow(selectedMatch), null);
});

test("clicking create adds a selected native option and emits change", () => {
    const select = selectFor();
    select.query = "test";
    select.filterLocalOptions("test");
    createRow(select).dispatchEvent({ type: "click" });

    assert.equal(select.element.options.length, 1);
    assert.equal(select.element.options[0].value, "test");
    assert.equal(select.element.options[0].selected, true);
    assert.equal(select.element.changeCount, 1);
    assert.equal(select.searchInput.value, "");
});

test("Enter creates a candidate when no option result is available", () => {
    const select = selectFor();
    select.query = "test";
    select.searchInput.value = "test";
    select.filterLocalOptions("test");
    let prevented = false;
    select.searchKeydown({ key: "Enter", preventDefault() { prevented = true; } });

    assert.equal(prevented, true);
    assert.equal(select.element.options[0].value, "test");
    assert.equal(select.element.options[0].selected, true);
    assert.equal(select.element.changeCount, 1);
});
