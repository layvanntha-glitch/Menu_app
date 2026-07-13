<?php
/**
 * account.php — the customer's dashboard: profile summary + order history.
 */
require_once __DIR__ . '/includes/auth_user.php';
require_once __DIR__ . '/includes/settings.php';

require_user('/menu/account.php');
$user = current_user();

// This customer's orders, newest first.
$stmt = $pdo->prepare(
    'SELECT id, order_type, status, total_amount, created_at
     FROM orders WHERE user_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$spent = 0.0;
foreach ($orders as $o) {
    if ($o['status'] !== 'cancelled') {
        $spent += (float) $o['total_amount'];
    }
}

$pageTitle = 'My Account';
require __DIR__ . '/includes/header.php';
?>

<div class="account-head">
    <div>
        <h1 class="page-title">Hi, <?= e($user['name']) ?> 👋</h1>
        <p class="page-subtitle"><?= e($user['email']) ?></p>
    </div>
    <a href="/menu/logout.php" class="btn btn--muted">Log out</a>
</div>

<div class="stat-grid" style="margin-bottom:28px;">
    <div class="stat-card"><span class="stat-icon">🧾</span><span class="stat-label">Orders</span><span class="stat-value"><?= count($orders) ?></span></div>
    <div class="stat-card"><span class="stat-icon">💰</span><span class="stat-label">Total Spent</span><span class="stat-value"><?= money($spent) ?></span></div>
    <div class="stat-card"><span class="stat-icon">🍽️</span><span class="stat-label">Ready to order?</span><span class="stat-value" style="font-size:1rem;"><a href="/menu/index.php" class="btn btn--sm">Browse menu →</a></span></div>
</div>

<div class="card">
    <h2 class="card-title">Order History</h2>
    <?php if (empty($orders)): ?>
        <p class="muted">You haven't placed any orders yet.
            <a href="/menu/index.php">Start browsing →</a></p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Type</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int) $o['id'] ?></td>
                        <td><?= $o['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?></td>
                        <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                        <td><?= money($o['total_amount']) ?></td>
                        <td><?= e(date('M j, Y H:i', strtotime($o['created_at']))) ?></td>
                        <td><a href="/menu/order_confirmation.php?id=<?= (int) $o['id'] ?>" class="btn btn--sm btn--ghost">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
