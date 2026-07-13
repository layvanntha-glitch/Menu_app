<?php
/**
 * Shared page header (top of every customer page).
 * Expects the including page to have already required functions.php.
 *
 * Optional variable the page may set before including this file:
 *   $pageTitle  — the browser tab title.
 */
require_once __DIR__ . '/auth_user.php';   // gives is_user() / current_user()
require_once __DIR__ . '/settings.php';    // restaurant_name(), brand_mark_html()
$pageTitle = $pageTitle ?? 'Our Menu';
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#e8552d">
    <title><?= e($pageTitle) ?> — <?= e(restaurant_name()) ?></title>
    <link rel="stylesheet" href="/menu/assets/css/style.css">
    <!-- Telegram Mini App SDK (no-op in a normal browser). -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        // Apply the saved theme before paint to avoid a flash of the wrong theme.
        (function () {
            try {
                var t = localStorage.getItem('tb-theme');
                if (t) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="/menu/index.php" class="brand"><span class="brand-mark"><?= brand_mark_html() ?></span> <?= e(restaurant_name()) ?></a>
            <nav class="main-nav">
                <a href="/menu/index.php" class="nav-link"><?= t('nav_menu') ?></a>
                <?php if (is_user()): $u = current_user(); ?>
                    <a href="/menu/favorites.php" class="nav-link" title="<?= t('nav_favourites') ?>">❤️</a>
                    <a href="/menu/account.php" class="nav-link">👤 <?= e(explode(' ', trim($u['name']))[0]) ?></a>
                <?php else: ?>
                    <a href="/menu/login.php" class="nav-link"><?= t('nav_signin') ?></a>
                <?php endif; ?>
                <?php require __DIR__ . '/lang_switch.php'; ?>
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">
                    <span class="icon-moon">🌙</span><span class="icon-sun">☀️</span>
                </button>
                <a href="/menu/cart.php" class="cart-link">
                    🛒 <span><?= t('nav_cart') ?></span>
                    <span class="cart-badge" data-count="<?= cart_count() ?>"><?= cart_count() ?></span>
                </a>
            </nav>
        </div>
    </header>
    <main class="container page-main">
