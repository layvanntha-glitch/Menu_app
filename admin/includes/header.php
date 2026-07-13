<?php
/**
 * Shared admin layout header. Assumes auth.php is already included and the
 * user is authenticated. The page may set $pageTitle and $activeNav before
 * including this file.
 */
require_once __DIR__ . '/../../includes/settings.php';   // restaurant_name(), brand_mark_html()
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';

// Small helper to mark the current nav link.
function nav_active(string $key, string $active): string
{
    return $key === $active ? ' class="active"' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Admin</title>
    <link rel="stylesheet" href="/menu/assets/css/style.css">
    <link rel="stylesheet" href="/menu/assets/css/admin.css">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('tb-theme');
                if (t) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body class="admin-body">
    <header class="admin-topbar">
        <div class="admin-topbar__inner">
            <a href="/menu/admin/index.php" class="admin-brand"><?= brand_mark_html('admin-brand-logo') ?> <?= e(restaurant_name()) ?> <span>Admin</span></a>
            <nav class="admin-nav">
                <?php if (!is_chef()): ?>
                    <a href="/menu/admin/index.php"<?= nav_active('dashboard', $activeNav) ?>><?= t('a_dashboard') ?></a>
                    <a href="/menu/admin/orders.php"<?= nav_active('orders', $activeNav) ?>><?= t('a_orders') ?></a>
                <?php endif; ?>
                <a href="/menu/admin/kitchen.php"<?= nav_active('kitchen', $activeNav) ?>><?= t('a_kitchen') ?></a>
                <?php if (!is_chef()): ?>
                    <a href="/menu/admin/categories.php"<?= nav_active('categories', $activeNav) ?>><?= t('a_categories') ?></a>
                    <a href="/menu/admin/items.php"<?= nav_active('items', $activeNav) ?>><?= t('a_items') ?></a>
                    <a href="/menu/admin/settings.php"<?= nav_active('settings', $activeNav) ?>><?= t('a_settings') ?></a>
                <?php endif; ?>
            </nav>
            <div class="admin-user">
                <?php require __DIR__ . '/../../includes/lang_switch.php'; ?>
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">
                    <span class="icon-moon">🌙</span><span class="icon-sun">☀️</span>
                </button>
                <a href="/menu/index.php" target="_blank" class="btn btn--sm btn--ghost"><?= t('a_view_site') ?></a>
                <span><?= e($_SESSION['admin']['username'] ?? '') ?>
                    <span class="role-badge role-<?= e(admin_role()) ?>"><?= is_chef() ? t('a_chef') : t('a_admin') ?></span>
                </span>
                <a href="/menu/admin/logout.php" class="btn btn--sm btn--muted"><?= t('a_logout') ?></a>
            </div>
        </div>
    </header>
    <main class="admin-main">
