import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = (await readFile(new URL("../../source/js/components/lemonade-admin-sidebar.js", import.meta.url), "utf8"))
    .replace(/^import .*;\n/gm, "");
const { AdminSidebar } = await import(`data:text/javascript;base64,${Buffer.from(source).toString("base64")}`);

/**
 * Creates a sidebar group with observable disclosure controls
 *
 * @param {boolean} isOpen
 * @returns {{ group: object, collapseCloses: Array<boolean>, dropdownCloseReasons: Array<string> }}
 */
function sidebarGroup(isOpen) {
    const collapseCloses = [];
    const dropdownCloseReasons = [];
    const group = {
        lemonadeCollapse: {
            close() { collapseCloses.push(true); },
            isOpen() { return isOpen; },
        },
        lemonadeDropdown: {
            close(reason) { dropdownCloseReasons.push(reason); },
        },
    };

    return { group, collapseCloses, dropdownCloseReasons };
}

test("opening an expanded navigation group closes the previously open group", () => {
    const active = sidebarGroup(true);
    const previous = sidebarGroup(true);
    const closed = sidebarGroup(false);
    const collapsedStates = [];
    const sidebar = Object.create(AdminSidebar.prototype);
    sidebar.groups = [previous.group, active.group, closed.group];
    sidebar.isCollapsed = false;
    sidebar.setGroupExpanded = (group, isExpanded) => collapsedStates.push({ group, isExpanded });

    sidebar.closeOtherGroups(active.group);

    assert.equal(previous.collapseCloses.length, 1);
    assert.equal(closed.collapseCloses.length, 0);
    assert.deepEqual(collapsedStates, [{ group: previous.group, isExpanded: false }]);
});

test("opening a collapsed navigation flyout closes the previous flyout", () => {
    const active = sidebarGroup(true);
    const previous = sidebarGroup(true);
    const sidebar = Object.create(AdminSidebar.prototype);
    sidebar.groups = [previous.group, active.group];
    sidebar.isCollapsed = true;

    sidebar.closeOtherGroups(active.group);

    assert.deepEqual(previous.dropdownCloseReasons, ["switch"]);
    assert.deepEqual(active.dropdownCloseReasons, []);
    assert.equal(previous.collapseCloses.length, 0);
});

test("mobile navigation always starts collapsed", () => {
    const sidebar = Object.create(AdminSidebar.prototype);
    sidebar.isMobile = () => true;

    assert.equal(sidebar.getInitialCollapsed(), true);
});

test("mobile toggle does not overwrite the desktop preference", () => {
    const states = [];
    const sidebar = Object.create(AdminSidebar.prototype);
    sidebar.isCollapsed = true;
    sidebar.isMobile = () => true;
    sidebar.setCollapsed = (isCollapsed, shouldPersist) => states.push({ isCollapsed, shouldPersist });

    sidebar.handleToggle();

    assert.deepEqual(states, [{ isCollapsed: false, shouldPersist: false }]);
});

test("leaving mobile restores the persisted desktop preference", () => {
    const states = [];
    const sidebar = Object.create(AdminSidebar.prototype);
    sidebar.getStoredCollapsed = () => false;
    sidebar.setCollapsed = (isCollapsed, shouldPersist) => states.push({ isCollapsed, shouldPersist });

    sidebar.handleViewportChange({ matches: false });

    assert.deepEqual(states, [{ isCollapsed: false, shouldPersist: false }]);
});
