<?php
/**
 * includes/lang_switch.php — the language selector (🌐 + current language).
 * Collapses to a single tappable button that opens a dropdown of languages,
 * so it stays clear and uncramped on phones. Uses a native <details> element
 * (no JavaScript required). Included in the storefront and admin headers.
 */
$__cur   = current_lang();
$__names = ['en' => 'English', 'km' => 'ភាសាខ្មែរ', 'zh' => '中文'];
?>
<details class="lang-switch">
    <summary class="lang-current" aria-label="<?= t('language') ?>">
        <span class="lang-globe" aria-hidden="true">🌐</span>
        <span class="lang-code"><?= e(TB_LANGS[$__cur] ?? 'EN') ?></span>
        <span class="lang-caret" aria-hidden="true">▾</span>
    </summary>
    <div class="lang-menu" role="menu">
        <?php foreach (TB_LANGS as $__code => $__label): ?>
            <a href="<?= e(lang_switch_url($__code)) ?>" role="menuitem" lang="<?= e($__code) ?>"
               class="lang-item<?= $__code === $__cur ? ' is-active' : '' ?>"
               <?= $__code === $__cur ? 'aria-current="true"' : '' ?>>
                <span class="lang-item__name"><?= e($__names[$__code] ?? $__label) ?></span>
                <span class="lang-item__code"><?= e($__label) ?></span>
                <?php if ($__code === $__cur): ?><span class="lang-item__check">✓</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</details>
<script>
(window.__tbLangInit || (window.__tbLangInit = function () {
    // Close any open language dropdown when tapping outside it or pressing Esc.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.lang-switch[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('details.lang-switch[open]').forEach(function (d) {
                d.removeAttribute('open');
            });
        }
    });
    return true;
})());
</script>
