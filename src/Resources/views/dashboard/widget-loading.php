<?php

declare(strict_types=1);

use Lemonade\Framework\View\View;

/** @var string $skeleton */
/** @var View $this */
?>
<?= $this->partial('admin::components.loading-skeleton', [
    'variant' => $skeleton,
    'rows' => 4,
    'loadingTextKey' => 'admin.dashboard.widgets.loading',
]) ?>
