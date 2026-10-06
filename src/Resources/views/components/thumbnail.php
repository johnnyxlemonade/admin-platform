<?php

declare(strict_types=1);

use Lemonade\Admin\Presentation\AdminThumbnail;

/**
 * @var AdminThumbnail $thumbnail
 */
$fileIdentity = $thumbnail->fileIdentity();
?>
<span class="lm-thumbnail lm-thumbnail--<?= e($thumbnail->size()) ?> lm-thumbnail--<?= e($thumbnail->shape()) ?>"<?php if ($fileIdentity !== null): ?> data-lemonade-file-module="<?= e($fileIdentity['module']) ?>" data-lemonade-file-entity="<?= e($fileIdentity['entity']) ?>" data-lemonade-file-usage="<?= e($fileIdentity['usage']) ?>" data-lemonade-file-presentation="<?= e($fileIdentity['presentation']) ?>" data-lemonade-file-alt="<?= e($thumbnail->alt()) ?>" data-lemonade-file-fallback="<?= e($thumbnail->fallback() ?? '') ?>"<?php endif; ?>>
    <?php if ($thumbnail->url() !== null): ?>
        <img src="<?= e($thumbnail->url()) ?>" alt="<?= e($thumbnail->alt()) ?>">
    <?php elseif ($thumbnail->fallback() !== null): ?>
        <span aria-hidden="true"><?= e($thumbnail->fallback()) ?></span>
    <?php endif; ?>
</span>
