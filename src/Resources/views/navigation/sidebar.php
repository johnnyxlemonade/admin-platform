<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Navigation\AdminNavigationEntryInterface;
use Lemonade\Admin\Navigation\AdminNavigationGroup;
use Lemonade\Admin\Navigation\AdminNavigationItem;
use Lemonade\Framework\View\RequestViewHelpers;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var RequestViewHelpers $requestHelpers */
/** @var list<AdminNavigationEntryInterface> $navigation */
?>
<nav
    aria-label="<?= e($helpers->lang('admin.sidebar.sections')) ?>"
    data-lemonade-i18n-aria-label="admin.sidebar.sections"
>
    <ul class="sidebar-nav">
        <?php foreach ($navigation as $entry): ?>
            <?php if ($entry instanceof AdminNavigationItem): ?>
                <?php
                $itemActive = $requestHelpers->isRouteActive(
                    $entry->route(),
                    $entry->routeParameters(),
                    $entry->routePrefix(),
                );
                $itemUrl = $helpers->url($entry->route(), $entry->routeParameters());
                ?>
                <li>
                    <a
                        class="nav-link<?= $itemActive ? ' is-active' : '' ?>"
                        href="<?= e($itemUrl) ?>"
                        data-lemonade-navigation-item-key="<?= e($entry->key()) ?>"
                    >
                        <span class="sidebar-nav-icon-wrap">
                            <i class="<?= e(($entry->icon() ?? AdminIcon::Circle)->cssClass()) ?> sidebar-nav-icon" aria-hidden="true"></i>
                        </span>
                        <span data-lemonade-navigation-item-label="<?= e($entry->key()) ?>">
                            <?= e($entry->label()) ?>
                        </span>
                    </a>
                </li>
                <?php continue; ?>
            <?php endif; ?>

            <?php if (!$entry instanceof AdminNavigationGroup): ?>
                <?php continue; ?>
            <?php endif; ?>

            <?php $groupActive = false; ?>
            <?php foreach ($entry->items() as $item): ?>
                <?php
                $itemActive = $requestHelpers->isRouteActive(
                    $item->route(),
                    $item->routeParameters(),
                    $item->routePrefix(),
                );
                ?>
                <?php if ($itemActive): ?>
                    <?php $groupActive = true; ?>
                    <?php break; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php $submenuId = 'sidebar-group-' . $entry->key() . '-panel'; ?>
            <li
                class="nav-group<?= $groupActive ? ' is-active-section is-expanded' : '' ?>"
                data-lemonade-sidebar-group
                data-lemonade-collapse
                data-lemonade-navigation-group-key="<?= e($entry->key()) ?>"
            >
                <button
                    class="nav-link nav-group-toggle<?= $groupActive ? ' is-active' : '' ?>"
                    type="button"
                    data-lemonade-sidebar-group-toggle
                    data-lemonade-collapse-trigger
                    aria-expanded="<?= $groupActive ? 'true' : 'false' ?>"
                    aria-controls="<?= e($submenuId) ?>"
                >
                    <span class="sidebar-nav-icon-wrap">
                        <i class="<?= e($entry->icon()->cssClass()) ?> sidebar-nav-icon" aria-hidden="true"></i>
                    </span>
                    <span
                        data-lemonade-sidebar-group-label
                        data-lemonade-navigation-group-label="<?= e($entry->key()) ?>"
                    >
                        <?= e($entry->label()) ?>
                    </span>
                    <i
                        class="<?= e(($groupActive ? AdminIcon::ChevronUp : AdminIcon::ChevronDown)->cssClass()) ?> sidebar-nav-chevron"
                        aria-hidden="true"
                    ></i>
                </button>
                <div
                    class="nav-submenu lm-collapse-panel"
                    id="<?= e($submenuId) ?>"
                    data-lemonade-sidebar-submenu
                    data-lemonade-collapse-panel
                    <?= $groupActive ? '' : 'hidden' ?>
                >
                    <div
                        class="sidebar-flyout-title"
                        aria-hidden="true"
                        data-lemonade-navigation-group-label="<?= e($entry->key()) ?>"
                    >
                        <?= e($entry->label()) ?>
                    </div>
                    <?php foreach ($entry->items() as $item): ?>
                        <?php
                        $itemActive = $requestHelpers->isRouteActive(
                            $item->route(),
                            $item->routeParameters(),
                            $item->routePrefix(),
                        );
                        $itemUrl = $helpers->url($item->route(), $item->routeParameters());
                        ?>
                        <a
                            <?= $itemActive ? 'class="is-active"' : '' ?>
                            href="<?= e($itemUrl) ?>"
                            data-lemonade-navigation-item-key="<?= e($item->key()) ?>"
                            data-lemonade-navigation-item-label="<?= e($item->key()) ?>"
                        >
                            <?= e($item->label()) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
