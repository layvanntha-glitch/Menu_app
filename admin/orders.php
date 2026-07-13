<?php
/**
 * admin/orders.php — view and manage customer orders.
 *
 * - List orders, optionally filtered by status (?status=pending etc.)
 * - Open one order's details with ?view=ID
 * - Update an order's status (POST action=status)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';   // currency symbol
require_once __DIR__ . '/../includes/telegram_notify.php';
require_admin_role();

// Valid statuses in workflow order.
$STATUSES = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];

// -------- Handle status updates --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $id     = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, $STATUSES, true)) {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);

        // Notify the customer on Telegram if this order came from the Mini App.
        $tg = $pdo->prepare('SELECT tg_chat_id FROM orders WHERE id = ?');
        $tg->execute([$id]);
        $chat = $tg->fetchColumn();
        if ($chat) {
            tg_notify_status($pdo, (string) $chat, $id, $status, setting('currency_symbol', '$'));
        }
    }
    // Preserve the view/filter context on redirect.
    $back = !empty($_POST['return_view'])
        ? '/menu/admin/orders.php?view=' . (int) $_POST['return_view']
        : '/menu/admin/orders.php' . (!empty($_POST['return_filter']) ? '?status=' . urlencode($_POST['return_filter']) : '');
    redirect($back);
}

// -------- Single order detail view --------
$viewId = (int) ($_GET['view'] ?? 0);
if ($viewId) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();

    if (!$order) {
        redirect('/menu/admin/orders.php');
    }

    $lineStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $lineStmt->execute([$viewId]);
    $lines = $lineStmt->fetchAll();

    $pageTitle = 'Order #' . $viewId;
    $activeNav = 'orders';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="toolbar">
        <h1 class="admin-h1" style="margin:0;">Order #<?= (int) $order['id'] ?></h1>
        <div style="display:flex;gap:8px;">
            <a href="/menu/admin/receipt.php?id=<?= (int) $order['id'] ?>" target="_blank" class="btn btn--sm">🖨 Print Receipt</a>
            <a href="/menu/admin/orders.php" class="btn btn--ghost btn--sm">← Back to orders</a>
        </div>
    </div>

    <div class="checkout-layout">
        <div class="card">
            <h2 class="card-title">Items</h2>
            <table class="data-table">
                <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><?= e($l['item_name']) ?></td>
                            <td><?= money($l['price']) ?></td>
                            <td><?= (int) $l['quantity'] ?></td>
                            <td><?= money($l['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right;">Subtotal</td>
                        <td><?= money($order['subtotal']) ?></td></tr>
                    <?php if ($order['tax_amount'] > 0): ?>
                        <tr><td colspan="3" style="text-align:right;">Tax</td>
                            <td><?= money($order['tax_amount']) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($order['service_amount'] > 0): ?>
                        <tr><td colspan="3" style="text-align:right;">Service Charge</td>
                            <td><?= money($order['service_amount']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td colspan="3" style="text-align:right;font-weight:600;">Total</td>
                        <td style="font-weight:700;color:var(--primary);"><?= money($order['total_amount']) ?></td></tr>
                </tfoot>
            </table>
        </div>

        <aside class="card">
            <h2 class="card-title">Details</h2>
            <p><strong>Customer:</strong> <?= e($order['customer_name']) ?></p>
            <p><strong>Phone:</strong> <?= e($order['phone'] ?: '—') ?></p>
            <p><strong>Type:</strong> <?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?></p>
            <?php if ($order['order_type'] === 'dine_in'): ?>
                <p><strong>Table:</strong> <?= e($order['table_number'] ?: '—') ?></p>
            <?php endif; ?>
            <p><strong>Placed:</strong> <?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></p>
            <?php if ($order['notes']): ?>
                <p><strong>Notes:</strong> <?= e($order['notes']) ?></p>
            <?php endif; ?>

            <hr style="margin:16px 0;border:none;border-top:1px solid var(--border);">
            <p style="margin-bottom:8px;"><strong>Status:</strong>
                <span class="status-pill status-<?= e($order['status']) ?>"><?= e($order['status']) ?></span>
            </p>
            <form method="post" action="/menu/admin/orders.php">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                <input type="hidden" name="return_view" value="<?= (int) $order['id'] ?>">
                <label class="field">
                    <span>Change status</span>
                    <select name="status">
                        <?php foreach ($STATUSES as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                <?= ucfirst($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn--block">Update Status</button>
            </form>
        </aside>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// -------- List view (with optional status filter) --------
$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all' && in_array($filter, $STATUSES, true)) {
    $stmt = $pdo->prepare(
        'SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$filter]);
    $orders = $stmt->fetchAll();
} else {
    $filter = 'all';
    $orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
}

$pageTitle = 'Orders';
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<h1 class="admin-h1">Orders</h1>

<div class="filter-tabs">
    <a href="/menu/admin/orders.php" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <?php foreach ($STATUSES as $s): ?>
        <a href="/menu/admin/orders.php?status=<?= $s ?>" class="<?= $filter === $s ? 'active' : '' ?>">
            <?= ucfirst($s) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if (empty($orders)): ?>
        <p class="muted">No orders<?= $filter !== 'all' ? ' with status "' . e($filter) . '"' : '' ?>.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th>Placed</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int) $o['id'] ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= $o['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?></td>
                        <td><?= money($o['total_amount']) ?></td>
                        <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                        <td><?= e(date('M j, H:i', strtotime($o['created_at']))) ?></td>
                        <td class="actions-cell">
                            <a href="/menu/admin/orders.php?view=<?= (int) $o['id'] ?>" class="btn btn--sm btn--ghost">View</a>
                            <a href="/menu/admin/receipt.php?id=<?= (int) $o['id'] ?>" target="_blank" class="btn btn--sm btn--muted">🖨</a>
                            <form method="post" action="/menu/admin/orders.php" class="inline-form">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                                <select name="status" onchange="this.form.submit()" class="qty-input" style="width:auto;">
                                    <?php foreach ($STATUSES as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
