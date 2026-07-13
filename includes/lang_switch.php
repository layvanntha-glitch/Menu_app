<?php
/**
 * includes/lang_switch.php — the compact EN / ខ្មែរ / 中文 language selector.
 * Included inside the storefront and admin headers. Preserves the current
 * page + query string, only swapping ?lang=.
 */
$__cur = current_lang();
?>
<div class="lang-switch" role="group" aria-label="<?= t('language') ?>">
    <?php foreach (TB_LANGS as $__code => $__label): ?>
        <a href="<?= e(lang_switch_url($__code)) ?>"
           class="lang-opt<?= $__code === $__cur ? ' is-active' : '' ?>"
           <?= $__code === $__cur ? 'aria-current="true"' : '' ?>
           lang="<?= e($__code) ?>"><?= e($__label) ?></a>
    <?php endforeach; ?>
</div>
