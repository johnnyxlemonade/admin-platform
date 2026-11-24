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
        if (child.parentNode) child.parentNode.children = child.parentNode.children.filter((item) => item !== child);
        this.children.push(child);
        child.parentNode = this;
        return child;
    }

    insertBefore(child, reference) {
        if (child.parentNode) child.parentNode.children = child.parentNode.children.filter((item) => item !== child);
        this.children.splice(this.children.indexOf(reference), 0, child);
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
globalThis.CustomEvent = class { constructor(type) { this.type = type; } };
globalThis.window = { location: { origin: "https://example.test" } };

const source = (await readFile(new URL("../../source/js/components/lemonade-select.js", import.meta.url), "utf8"))
    .replace(/^import .*;\n/gm, "")
    .replace(
        "export class Select",
        "const BootstrapIcons = { className: () => 'icon' };\nconst DomHelper = { queryAll: () => [] };\nconst EventHelper = { debounce: (callback) => callback, on: () => () => {}, animationFrame: () => ({ cancel() {} }) };\nconst HttpHelper = { get: (...args) => globalThis.selectTestGet(...args) };\nconst I18n = { t: (key, params = {}) => key.replace('{name}', params.name || '{name}') };\nexport class Select",
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

test("ordered async select preserves and changes native submission order", () => {
    const select = selectFor([option("a", true), option("b", true)]);
    select.allowCreate = false;
    select.ordered = true;
    select.clearable = false;
    select.setOptions([{ value: "b", label: "B" }, { value: "a", label: "A" }, { value: "c", label: "C" }], "empty");
    assert.deepEqual(select.selectedOptions().map((item) => item.value), ["a", "b"]);
    const add = select.element.options.find((item) => item.value === "c");
    select.selectOption(add);
    assert.deepEqual(select.selectedOptions().map((item) => item.value), ["a", "b", "c"]);
    const secondToken = select.trigger.children[1];
    secondToken.children[1].dispatchEvent({ type: "click", preventDefault() {}, stopPropagation() {} });
    assert.deepEqual(select.selectedOptions().map((item) => item.value), ["b", "a", "c"]);
    assert.equal(select.trigger.children[0].children[1].disabled, true);
    select.trigger.children[0].children[2].dispatchEvent({ type: "click", preventDefault() {}, stopPropagation() {} });
    assert.deepEqual(select.selectedOptions().map((item) => item.value), ["a", "b", "c"]);
});

test("clearable async single select offers an empty value after search", () => {
    const select = selectFor([option("a", true)]);
    select.element.multiple = false;
    select.allowCreate = false;
    select.ordered = false;
    select.clearable = true;
    select.setOptions([{ value: "b", label: "B" }], "empty");
    const blank = select.element.options.find((item) => item.value === "");
    assert.ok(blank);
    select.close = () => {};
    select.selectOption(blank);
    assert.equal(select.selectedOptions()[0].value, "");
});

test("async filter preserves its all-values option after search", () => {
    const select = selectFor([option("", true)]);
    select.element.multiple = false;
    select.allowCreate = false;
    select.ordered = false;
    select.clearable = true;
    select.emptyOptionLabel = "Všechny hodnoty";
    select.setOptions([{ value: "42", label: "Praha" }], "empty");

    assert.equal(select.element.options[0].value, "");
    assert.equal(select.element.options[0].text, "Všechny hodnoty");
});

test("async filter resolves its selected record label without changing search results", async () => {
    const select = selectFor([option("42", true)]);
    select.element.multiple = false;
    select.element.value = "42";
    select.sourceType = "async";
    select.source = "/relations?field=3";
    select.searchParam = "q";
    select.allowCreate = false;
    select.destroyed = false;
    let resolved = false;
    select.element.addEventListener("lemonade:select:label-resolved", () => { resolved = true; });
    globalThis.selectTestGet = async (url) => {
        assert.equal(new URL(url).searchParams.get("selected"), "42");
        return { items: [{ value: "42", label: "Praha" }] };
    };

    await select.resolveSelectedValue();

    assert.equal(select.element.options[0].text, "Praha");
    assert.equal(select.element.value, "42");
    assert.equal(resolved, true);
    assert.deepEqual(select.resultOptions, [{ value: "42", label: "42" }]);
});

test("async filter retries label resolution after a failed request", async () => {
    const select = selectFor([option("42", true)]);
    select.element.multiple = false;
    select.element.value = "42";
    select.sourceType = "async";
    select.source = "/relations?field=3";
    select.destroyed = false;
    let requests = 0;
    globalThis.selectTestGet = async () => {
        requests += 1;
        if (requests === 1) {
            throw new Error("Temporary failure");
        }
        return { items: [{ value: "42", label: "Praha" }] };
    };

    await select.resolveSelectedValue();
    await select.resolveSelectedValue();

    assert.equal(requests, 2);
    assert.equal(select.element.options[0].text, "Praha");
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
