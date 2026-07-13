<?php
/**
 * admin/index.php — the admin dashboard (landing page after login).
 * Shows quick stats and the latest orders.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';   // currency symbol
require_admin_role();   // admins only (chefs are sent to the Kitchen Display)

// Gather some quick counts for the stat cards.
$stats = [
    'orders_today'  => $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE date(created_at) = date('now','localtime')"
    )->fetchColumn(),
    'orders_pending'=> $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE status IN ('pending','preparing','ready')"
    )->fetchColumn(),
    'items_total'   => $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn(),
    'revenue_today' => $pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM orders
         WHERE date(created_at) = date('now','localtime') AND status <> 'cancelled'"
    )->fetchColumn(),
];

// Latest 5 orders.
$recent = $pdo->query(
    "SELECT id, customer_name, order_type, status, total_amount, created_at
     FROM orders ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

// Orders-by-food: units sold + revenue per dish (excluding cancelled orders).
$byFood = $pdo->query(
    "SELECT oi.item_name AS name,
            SUM(oi.quantity) AS qty,
            SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled'
     GROUP BY oi.item_name
     ORDER BY qty DESC, revenue DESC
     LIMIT 8"
)->fetchAll();

$maxQty = 0;
foreach ($byFood as $f) {
    $maxQty = max($maxQty, (int) $f['qty']);
}
$topSeller = $byFood[0] ?? null;

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<h1 class="admin-h1"><?= t('a_dashboard') ?></h1>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-icon">🧾</span>
        <span class="stat-label"><?= t('a_orders_today') ?></span>
        <span class="stat-value"><?= (int) $stats['orders_today'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">🔥</span>
        <span class="stat-label"><?= t('a_active_orders') ?></span>
        <span class="stat-value"><?= (int) $stats['orders_pending'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">🍽️</span>
        <span class="stat-label"><?= t('a_menu_items') ?></span>
        <span class="stat-value"><?= (int) $stats['items_total'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">💰</span>
        <span class="stat-label"><?= t('a_revenue_today') ?></span>
        <span class="stat-value"><?= money($stats['revenue_today']) ?></span>
    </div>
</div>

<?php if ($topSeller): ?>
<div class="dash-analytics">
    <!-- Top seller banner -->
    <div class="topseller" id="topSeller">
        <div class="topseller__shine"></div>
        <span class="topseller__crown">👑</span>
        <div class="topseller__body">
            <span class="topseller__eyebrow"><?= t('a_top_seller') ?></span>
            <h3 class="topseller__name"><?= e($topSeller['name']) ?></h3>
            <div class="topseller__stats">
                <span class="topseller__num" data-count="<?= (int) $topSeller['qty'] ?>">0</span>
                <span class="topseller__unit"><?= t('a_sold') ?></span>
                <span class="topseller__rev"><?= money($topSeller['revenue']) ?> <?= t('a_earned') ?></span>
            </div>
        </div>
    </div>

    <!-- Orders by food chart -->
    <div class="card chart-card">
        <div class="card-head">
            <h2 class="card-title"><?= t('a_orders_food') ?></h2>
            <span class="muted" style="font-size:.82rem;"><?= t('a_units_sold') ?></span>
        </div>
        <div class="foodchart" id="foodChart">
            <?php foreach ($byFood as $i => $f): ?>
                <?php $pct = $maxQty > 0 ? round((int) $f['qty'] / $maxQty * 100) : 0; ?>
                <div class="foodbar <?= $i === 0 ? 'foodbar--top' : '' ?>" style="--i:<?= $i ?>;">
                    <div class="foodbar__label" title="<?= e($f['name']) ?>">
                        <?= $i === 0 ? '👑 ' : '' ?><?= e($f['name']) ?>
                    </div>
                    <div class="foodbar__track">
                        <div class="foodbar__fill" style="--target:<?= $pct ?>%;">
                            <span class="foodbar__val"><?= (int) $f['qty'] ?></span>
                        </div>
                    </div>
                    <div class="foodbar__rev"><?= money($f['revenue']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function () {
    // Grow the bars once they're on screen.
    var chart = document.getElementById('foodChart');
    if (chart) { requestAnimationFrame(function () {
        setTimeout(function () { chart.classList.add('is-animate'); }, 60);
    }); }

    // Count up the top-seller number.
    var num = document.querySelector('.topseller__num');
    if (num) {
        var target = parseInt(num.getAttribute('data-count'), 10) || 0;
        var start = null, dur = 1100;
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            num.textContent = Math.round(eased * target);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
})();
</script>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2 class="card-title"><?= t('a_recent_orders') ?></h2>
        <a href="/menu/admin/orders.php" class="btn btn--sm btn--ghost"><?= t('a_view_all') ?></a>
    </div>

    <?php if (empty($recent)): ?>
        <p class="muted"><?= t('a_no_orders') ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>#</th><th><?= t('a_customer') ?></th><th><?= t('a_type') ?></th><th><?= t('status') ?></th><th><?= t('total') ?></th><th><?= t('a_time') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td>#<?= (int) $o['id'] ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= $o['order_type'] === 'dine_in' ? t('dine_in') : t('takeaway') ?></td>
                        <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                        <td><?= money($o['total_amount']) ?></td>
                        <td><?= e(date('M j, H:i', strtotime($o['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
