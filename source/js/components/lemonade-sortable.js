/*!
 * Lemonade Sortable
 *
 * Provides shared sortable list interactions.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Proprietary - see LICENSE.md
 */

export class Sortable {
        constructor(element) {
            this.element = element;
            this.armedItem = null;
            this.bind();
        }

        getItems() { return Array.from(this.element.querySelectorAll(":scope > [data-lemonade-sortable-item]")); }
        getItem(target) { const item = target.closest("[data-lemonade-sortable-item]"); return item && item.parentElement === this.element ? item : null; }
        getHandle(target) { return target.closest("[data-lemonade-sortable-handle]"); }
        group() { return this.element.getAttribute("data-lemonade-sortable-group") || ""; }
        isCompatible(source) { return source === this || (source.group() && source.group() === this.group()); }

        bind() {
            this.element.addEventListener("pointerdown", (event) => {
                const handle = this.getHandle(event.target);
                const item = this.getItem(event.target);
                if (!handle || !item) { return; }
                this.armedItem = item;
                item.draggable = true;
            });
            this.element.addEventListener("pointerup", () => this.clearDraggable());
            document.addEventListener("pointerup", () => this.clearDraggable());
            document.addEventListener("pointercancel", () => this.clearDraggable());
            this.element.addEventListener("dragstart", (event) => {
                const item = this.getItem(event.target);
                if (!item) { return; }
                if (item !== this.armedItem) { event.preventDefault(); return; }
                Sortable.active = { item: item, source: this, id: item.getAttribute("data-lemonade-sortable-item") };
                item.classList.add("is-dragging");
                this.element.classList.add("is-sorting");
                event.dataTransfer.effectAllowed = "move";
                event.dataTransfer.setData("text/plain", Sortable.active.id);
            });
            this.element.addEventListener("dragover", (event) => {
                const active = Sortable.active;
                if (!active || !this.isCompatible(active.source)) { return; }
                event.preventDefault();
                const target = this.getItem(event.target);
                if (!target) { this.element.append(active.item); this.setDropTarget(null); return; }
                if (target === active.item) { return; }
                const before = event.clientY < target.getBoundingClientRect().top + (target.offsetHeight / 2);
                this.element.insertBefore(active.item, before ? target : target.nextSibling);
                this.setDropTarget(target);
            });
            this.element.addEventListener("drop", (event) => {
                const active = Sortable.active;
                if (!active || !this.isCompatible(active.source)) { return; }
                event.preventDefault();
                const source = active.source;
                this.finish();
                this.emitChange(source);
            });
            this.element.addEventListener("dragend", () => this.finish());
        }

        setDropTarget(target) { this.getItems().forEach((item) => item.classList.toggle("is-drop-target", item === target)); }
        clearDraggable() { this.getItems().forEach((item) => { item.draggable = false; }); this.armedItem = null; }
        emitChange(source) {
            const sourceItems = source.getItems().map((item) => item.getAttribute("data-lemonade-sortable-item"));
            this.element.dispatchEvent(new CustomEvent("lemonade:sortable:change", { bubbles: true, detail: { orderedIds: this.getItems().map((item) => item.getAttribute("data-lemonade-sortable-item")), sourceElement: source.element, sourceOrderedIds: sourceItems, group: this.group() } }));
        }
        finish() {
            const active = Sortable.active;
            if (!active) { return; }
            active.item.classList.remove("is-dragging");
            active.item.draggable = false;
            document.querySelectorAll("[data-lemonade-sortable]").forEach((element) => { element.classList.remove("is-sorting"); element.querySelectorAll(":scope > [data-lemonade-sortable-item]").forEach((item) => item.classList.remove("is-drop-target")); });
            document.querySelectorAll("[data-lemonade-sortable-drop-zone]").forEach((zone) => zone.classList.remove("is-drop-target"));
            active.source.clearDraggable();
            Sortable.active = null;
        }

        static initAll(context) {
            const scope = context || document;
            const elements = [];
            if (scope.matches && scope.matches("[data-lemonade-sortable]")) { elements.push(scope); }
            elements.push(...scope.querySelectorAll("[data-lemonade-sortable]"));
            elements.forEach((element) => { if (!element.lemonadeSortable) { element.lemonadeSortable = new Sortable(element); } });
            Sortable.bindDropZones();
        }

        static mount(root = document) {
            this.initAll(root);
        }

        static destroy(root = document) {
            const elements = root.matches && root.matches("[data-lemonade-sortable]") ? [root] : [];
            elements.push(...root.querySelectorAll("[data-lemonade-sortable]"));
            elements.forEach(function (element) {
                if (Sortable.active && Sortable.active.source.element === element) {
                    Sortable.active = null;
                }
                delete element.lemonadeSortable;
            });
        }

        static bindDropZones() {
            if (Sortable.dropZonesBound) { return; }
            Sortable.dropZonesBound = true;
            document.addEventListener("dragover", (event) => {
                const zone = event.target.closest("[data-lemonade-sortable-drop-zone]");
                const active = Sortable.active;
                if (!zone || !active || zone.getAttribute("data-lemonade-sortable-group") !== active.source.group()) { return; }
                event.preventDefault();
                document.querySelectorAll("[data-lemonade-sortable-drop-zone]").forEach((item) => item.classList.toggle("is-drop-target", item === zone));
            });
            document.addEventListener("drop", (event) => {
                const zone = event.target.closest("[data-lemonade-sortable-drop-zone]");
                const active = Sortable.active;
                if (!zone || !active || zone.getAttribute("data-lemonade-sortable-group") !== active.source.group()) { return; }
                event.preventDefault();
                const source = active.source;
                const targetSelector = zone.getAttribute("data-lemonade-sortable-drop-zone-target");
                const target = targetSelector ? document.querySelector(targetSelector) : null;
                if (target && target.lemonadeSortable && target.lemonadeSortable.isCompatible(source)) { target.append(active.item); target.lemonadeSortable.finish(); target.lemonadeSortable.emitChange(source); return; }
                source.finish();
                zone.dispatchEvent(new CustomEvent("lemonade:sortable:drop-zone", { bubbles: true, detail: { itemId: active.id, sourceElement: source.element, sourceOrderedIds: source.getItems().map((item) => item.getAttribute("data-lemonade-sortable-item")), group: source.group() } }));
            });
        }
}

    Sortable.active = null;
    Sortable.dropZonesBound = false;

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", function () { Sortable.initAll(); }); } else { Sortable.initAll(); }

export function mount(root = document) {
    Sortable.mount(root);
}

export function destroy(root = document) {
    Sortable.destroy(root);
}
