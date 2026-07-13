<?php
/**
 * index.php — the customer-facing menu.
 *
 * It reads every active category and its available items from the database,
 * then displays them grouped by category with an "Add to cart" button.
 */

require_once __DIR__ . '/config/db.php';        // gives us $pdo
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php'; // currency symbol

// Read the (optional) filters from the URL.
$search   = trim($_GET['q'] ?? '');          // search text
$activeCat = (int) ($_GET['cat'] ?? 0);       // selected category id (0 = all)

// 1) Fetch all active categories in display order (for the filter pills).
$categories = $pdo->query(
    'SELECT id, name
     FROM categories
     WHERE is_active = 1
     ORDER BY sort_order, name'
)->fetchAll();

// 2) Fetch available items, applying the search + category filters in SQL.
//    We build the WHERE clause dynamically but still use bound parameters,
//    so it stays safe from SQL injection.
$sql = 'SELECT id, category_id, name, description, price, discount_type, discount_value, image_path, is_available
        FROM menu_items
        WHERE is_available = 1';
$params = [];

if ($search !== '') {
    // Match the search text against name OR description.
    // Note: two separate placeholders because real prepared statements
    // (emulation off) do not allow reusing the same named placeholder.
    $sql .= ' AND (name LIKE :qname OR description LIKE :qdesc)';
    $params[':qname'] = '%' . $search . '%';
    $params[':qdesc'] = '%' . $search . '%';
}
if ($activeCat > 0) {
    $sql .= ' AND category_id = :cat';
    $params[':cat'] = $activeCat;
}
$sql .= ' ORDER BY name';

$itemStmt = $pdo->prepare($sql);
$itemStmt->execute($params);

$itemsByCategory = [];
$totalFound = 0;
$dealItems = [];   // items with an active discount (for the Special Offers group)
foreach ($itemStmt as $item) {
    $itemsByCategory[$item['category_id']][] = $item;
    $totalFound++;
    if (has_discount($item)) {
        $dealItems[] = $item;
    }
}

// A brief success message can be passed via ?added=1 after adding to cart.
$justAdded = isset($_GET['added']);

// Are any filters currently applied?
$isFiltering = ($search !== '' || $activeCat > 0);

// ---------------------------------------------------------------
// Featured item for the promo popup (only on the unfiltered menu):
// prefer the biggest active discount; otherwise the current best-seller.
// ---------------------------------------------------------------
$featured = null;
$featuredKind = '';
if (!$isFiltering) {
    if (!empty($dealItems)) {
        $best = null; $bestSaving = 0;
        foreach ($dealItems as $d) {
            $saving = (float) $d['price'] - effective_price($d);
            if ($saving > $bestSaving) { $bestSaving = $saving; $best = $d; }
        }
        $featured = $best;
        $featuredKind = 'deal';
    } else {
        // Best-seller: the available item that appears in the most orders.
        $bs = $pdo->query(
            "SELECT m.id, m.name, m.description, m.price, m.discount_type, m.discount_value,
                    m.image_path, COUNT(oi.id) AS sold
             FROM menu_items m
             JOIN order_items oi ON oi.menu_item_id = m.id
             WHERE m.is_available = 1
             GROUP BY m.id
             ORDER BY sold DESC, m.name
             LIMIT 1"
        )->fetch();
        if ($bs && (int) $bs['sold'] > 0) {
            $featured = $bs;
            $featuredKind = 'bestseller';
        }
    }
}

$pageTitle = 'Our Menu';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$isFiltering): ?>
<!-- Hero banner (hidden while searching/filtering) -->
<section class="hero">
    <span class="hero__eyebrow"><?= t('hero_eyebrow') ?></span>
    <h1 class="hero__title"><?= t('hero_title') ?></h1>
    <p class="hero__text"><?= t('hero_text') ?></p>
    <a href="#menu" class="hero__cta"><?= t('hero_cta') ?></a>
</section>
<?php else: ?>
<h1 class="page-title"><?= t('our_menu') ?></h1>
<p class="page-subtitle"><?= t('menu_subtitle') ?></p>
<?php endif; ?>

<div id="menu"></div>

<!-- Search + category filter toolbar -->
<div class="menu-toolbar">
    <form method="get" action="/menu/index.php" class="search-form" role="search">
        <?php if ($activeCat > 0): ?>
            <input type="hidden" name="cat" value="<?= (int) $activeCat ?>">
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($search) ?>"
               placeholder="<?= t('search_ph') ?>" class="search-input">
        <button type="submit" class="btn"><?= t('search') ?></button>
        <?php if ($isFiltering): ?>
            <a href="/menu/index.php" class="btn btn--muted"><?= t('clear') ?></a>
        <?php endif; ?>
    </form>

    <div class="filter-tabs">
        <?php
        // Preserve the current search when clicking a category pill.
        $qs = $search !== '' ? '&q=' . urlencode($search) : '';
        ?>
        <a href="/menu/index.php<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>"
           class="<?= $activeCat === 0 ? 'active' : '' ?>"><?= t('all') ?></a>
        <?php foreach ($categories as $cat): ?>
            <a href="/menu/index.php?cat=<?= (int) $cat['id'] ?><?= $qs ?>"
               class="<?= $activeCat === (int) $cat['id'] ? 'active' : '' ?>">
                <?= e($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($isFiltering): ?>
    <p class="page-subtitle" style="margin-bottom:20px;">
        <?= t('n_found', (int) $totalFound) ?>
        <?php if ($search !== ''): ?> <?= t('for_q') ?> “<strong><?= e($search) ?></strong>”<?php endif; ?>.
    </p>
<?php endif; ?>

<?php if (empty($categories)): ?>
    <div class="empty-state">
        <h2><?= t('no_menu') ?></h2>
        <p><?= t('check_back') ?></p>
    </div>
<?php elseif ($totalFound === 0): ?>
    <div class="empty-state">
        <p class="empty-emoji">🔍</p>
        <h2><?= t('no_dishes') ?></h2>
        <p><?= t('try_diff') ?></p>
        <p style="margin-top:20px;"><a href="/menu/index.php" class="btn"><?= t('show_all') ?></a></p>
    </div>
<?php else: ?>
    <?php if (!$isFiltering && !empty($dealItems)): ?>
        <section class="category-block special-offers" id="deals">
            <h2 class="category-title">
                <span class="offers-flame">🔥</span> <?= t('special_offers') ?>
                <span class="offers-count"><?= t('n_deals', count($dealItems)) ?></span>
            </h2>
            <div class="menu-grid">
                <?php foreach ($dealItems as $item): ?>
                    <article class="menu-card menu-card--deal">
                        <a class="menu-card__media" href="/menu/food.php?id=<?= (int) $item['id'] ?>" aria-label="<?= e($item['name']) ?> details">
                            <span class="deal-ribbon"><?= e(discount_badge($item)) ?></span>
                            <?php if (!empty($item['image_path'])): ?>
                                <img class="menu-card__img" loading="lazy"
                                     src="/menu/<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>">
                            <?php else: ?>
                                <div class="menu-card__img--placeholder">🍴</div>
                            <?php endif; ?>
                        </a>
                        <div class="menu-card__body">
                            <h3 class="menu-card__name">
                                <a href="/menu/food.php?id=<?= (int) $item['id'] ?>"><?= e($item['name']) ?></a>
                            </h3>
                            <p class="menu-card__desc"><?= e($item['description']) ?></p>
                            <div class="menu-card__footer">
                                <?= price_html($item) ?>
                                <form method="post" action="/menu/cart.php">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="btn btn--sm">＋ <?= t('add') ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($categories as $cat): ?>
        <?php
        $items = $itemsByCategory[$cat['id']] ?? [];
        if (empty($items)) {
            continue;   // skip categories with no available items
        }
        ?>
        <section class="category-block">
            <h2 class="category-title"><?= e($cat['name']) ?></h2>
            <div class="menu-grid">
                <?php foreach ($items as $item): ?>
                    <article class="menu-card">
                        <a class="menu-card__media" href="/menu/food.php?id=<?= (int) $item['id'] ?>" aria-label="<?= e($item['name']) ?> details">
                        <?php if (!empty($item['image_path'])): ?>
                            <img class="menu-card__img" loading="lazy"
                                 src="/menu/<?= e($item['image_path']) ?>"
                                 alt="<?= e($item['name']) ?>">
                        <?php else: ?>
                            <div class="menu-card__img--placeholder">🍴</div>
                        <?php endif; ?>
                        </a>

                        <div class="menu-card__body">
                            <h3 class="menu-card__name">
                                <a href="/menu/food.php?id=<?= (int) $item['id'] ?>"><?= e($item['name']) ?></a>
                            </h3>
                            <p class="menu-card__desc"><?= e($item['description']) ?></p>
                            <div class="menu-card__footer">
                                <?= price_html($item) ?>
                                <form method="post" action="/menu/cart.php">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="btn btn--sm">＋ <?= t('add') ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($featured): ?>
<!-- Promo popup: shown once per browser session -->
<div class="promo-overlay" id="promoOverlay" hidden>
    <div class="promo-modal" role="dialog" aria-modal="true" aria-labelledby="promoTitle">
        <button type="button" class="promo-close" id="promoClose" aria-label="Close">×</button>
        <span class="promo-tag <?= $featuredKind === 'deal' ? 'promo-tag--deal' : 'promo-tag--star' ?>">
            <?= $featuredKind === 'deal' ? t('promo_deal') : t('promo_fav') ?>
        </span>
        <a class="promo-media" href="/menu/food.php?id=<?= (int) $featured['id'] ?>">
            <?php if (!empty($featured['image_path'])): ?>
                <img src="/menu/<?= e($featured['image_path']) ?>" alt="<?= e($featured['name']) ?>">
            <?php else: ?>
                <div class="menu-card__img--placeholder">🍴</div>
            <?php endif; ?>
            <?php if ($featuredKind === 'deal'): ?>
                <span class="deal-ribbon"><?= e(discount_badge($featured)) ?></span>
            <?php endif; ?>
        </a>
        <h3 class="promo-name" id="promoTitle"><?= e($featured['name']) ?></h3>
        <p class="promo-desc"><?= e($featured['description']) ?></p>
        <div class="promo-price"><?= price_html($featured, 'promo-price__now') ?></div>
        <div class="promo-actions">
            <form method="post" action="/menu/cart.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="item_id" value="<?= (int) $featured['id'] ?>">
                <button type="submit" class="btn btn--block"><?= t('add_to_cart_p') ?></button>
            </form>
            <a href="/menu/food.php?id=<?= (int) $featured['id'] ?>" class="btn btn--ghost btn--block"><?= t('view_details') ?></a>
        </div>
    </div>
</div>
<script>
(function () {
    var KEY = 'tb_promo_seen_<?= (int) $featured['id'] ?>';
    try { if (sessionStorage.getItem(KEY)) return; } catch (e) {}
    var overlay = document.getElementById('promoOverlay');
    if (!overlay) return;
    function close() {
        overlay.hidden = true;
        try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    }
    // Reveal shortly after load so it feels intentional, not jarring.
    setTimeout(function () { overlay.hidden = false; }, 900);
    document.getElementById('promoClose').addEventListener('click', close);
    overlay.addEventListener('click', function (ev) { if (ev.target === overlay) close(); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
