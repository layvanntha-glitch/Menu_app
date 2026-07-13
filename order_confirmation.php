<?php
/**
 * order_confirmation.php — thank-you page shown after an order is placed.
 * Reads the order + its items back from the database by id and displays them.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';   // currency symbol

$orderId = (int) ($_GET['id'] ?? 0);

// Load the order header.
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    $pageTitle = 'Order Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>Order not found</h2>'
       . '<p><a class="btn" href="/menu/index.php">Back to Menu</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Load the order's line items.
$itemsStmt = $pdo->prepare(
    'SELECT item_name, price, quantity, subtotal FROM order_items WHERE order_id = ?'
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$pageTitle = t('order_confirmed');
require __DIR__ . '/includes/header.php';
?>

<div class="confirm-box card">
    <div class="confirm-check">✓</div>
    <h1 class="page-title" style="text-align:center;"><?= t('thank_you') ?>, <?= e($order['customer_name']) ?>!</h1>
    <p style="text-align:center;color:var(--gray);margin-bottom:24px;">
        <?= t('order_word') ?> <strong>#<?= (int) $order['id'] ?></strong> <?= t('order_received') ?>
    </p>

    <div class="confirm-meta">
        <div><span><?= t('order_type') ?></span><strong><?= $order['order_type'] === 'dine_in' ? t('dine_in') : t('takeaway') ?></strong></div>
        <?php if ($order['order_type'] === 'dine_in' && $order['table_number']): ?>
            <div><span><?= t('table') ?></span><strong><?= e($order['table_number']) ?></strong></div>
        <?php endif; ?>
        <div><span><?= t('status') ?></span><strong style="text-transform:capitalize;"><?= e($order['status']) ?></strong></div>
    </div>

    <ul class="summary-list">
        <?php foreach ($items as $it): ?>
            <li>
                <span><?= (int) $it['quantity'] ?>× <?= e($it['item_name']) ?></span>
                <span><?= money($it['subtotal']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="summary-breakdown">
        <div><span><?= t('th_subtotal') ?></span><span><?= money($order['subtotal']) ?></span></div>
        <?php if ($order['tax_amount'] > 0): ?>
            <div><span><?= t('tax') ?></span><span><?= money($order['tax_amount']) ?></span></div>
        <?php endif; ?>
        <?php if ($order['service_amount'] > 0): ?>
            <div><span><?= t('service') ?></span><span><?= money($order['service_amount']) ?></span></div>
        <?php endif; ?>
    </div>
    <div class="summary-total">
        <span><?= t('total') ?></span>
        <span><?= money($order['total_amount']) ?></span>
    </div>

    <?php if (!empty($order['notes'])): ?>
        <p class="confirm-notes"><strong><?= t('notes') ?>:</strong> <?= e($order['notes']) ?></p>
    <?php endif; ?>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:28px;">
        <a href="/menu/invoice.php?id=<?= (int) $order['id'] ?>" class="btn" target="_blank"><?= t('view_invoice') ?></a>
        <a href="/menu/invoice.php?id=<?= (int) $order['id'] ?>&pdf=1" class="btn btn--muted"><?= t('download_pdf') ?></a>
        <a href="/menu/index.php" class="btn btn--ghost"><?= t('order_more') ?></a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
