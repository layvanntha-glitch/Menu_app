<?php
/**
 * admin/receipt.php — a clean, print-friendly receipt for one order.
 *
 * Opened with ?id=ORDER_ID. Designed to print on narrow receipt paper
 * (or A4). It deliberately does NOT use the admin layout/header so the
 * printout stays minimal.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo 'Order not found. <a href="/menu/admin/orders.php">Back</a>';
    exit;
}

$lineStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$lineStmt->execute([$id]);
$lines = $lineStmt->fetchAll();

$restaurantName = setting('restaurant_name', 'Tasty Bites');
// Auto-open the print dialog when ?print=1 is present.
$autoPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — Order #<?= (int) $order['id'] ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Courier New", monospace;
            background: #eef0f2;
            color: #111;
            padding: 24px;
        }
        .receipt {
            width: 320px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
        }
        .r-center { text-align: center; }
        .r-name { font-size: 1.2rem; font-weight: bold; letter-spacing: 1px; }
        .r-sub { font-size: 0.75rem; color: #555; margin-top: 2px; }
        .r-divider { border-top: 1px dashed #888; margin: 12px 0; }
        .r-meta { font-size: 0.78rem; line-height: 1.6; }
        .r-meta span { display: inline-block; min-width: 70px; color: #555; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th, td { padding: 3px 0; text-align: left; }
        th { border-bottom: 1px solid #000; font-size: 0.72rem; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        .r-line { display: flex; justify-content: space-between; font-size: 0.82rem; padding: 2px 0; }
        .r-total { display: flex; justify-content: space-between; font-weight: bold; font-size: 1rem;
                   border-top: 2px solid #000; margin-top: 6px; padding-top: 6px; }
        .r-thanks { text-align: center; font-size: 0.8rem; margin-top: 14px; }
        .r-actions { width: 320px; margin: 16px auto 0; display: flex; gap: 8px; }
        .r-btn {
            flex: 1; padding: 10px; text-align: center; cursor: pointer;
            border: none; border-radius: 6px; font-size: 0.9rem; text-decoration: none;
            font-family: system-ui, sans-serif;
        }
        .r-btn--print { background: #e8552d; color: #fff; }
        .r-btn--back { background: #6b7280; color: #fff; }

        /* When printing: show ONLY the receipt, no buttons, no background. */
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; width: 100%; max-width: 320px; }
            .r-actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="r-center">
            <div class="r-name"><?= e($restaurantName) ?></div>
            <div class="r-sub">Order Receipt</div>
        </div>

        <div class="r-divider"></div>

        <div class="r-meta">
            <div><span>Order #:</span> <?= (int) $order['id'] ?></div>
            <div><span>Date:</span> <?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></div>
            <div><span>Customer:</span> <?= e($order['customer_name']) ?></div>
            <div><span>Type:</span> <?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?></div>
            <?php if ($order['order_type'] === 'dine_in' && $order['table_number']): ?>
                <div><span>Table:</span> <?= e($order['table_number']) ?></div>
            <?php endif; ?>
        </div>

        <div class="r-divider"></div>

        <table>
            <thead>
                <tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                    <tr>
                        <td><?= e($l['item_name']) ?></td>
                        <td class="num"><?= (int) $l['quantity'] ?></td>
                        <td class="num"><?= money($l['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="r-divider"></div>

        <div class="r-line"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
        <?php if ($order['tax_amount'] > 0): ?>
            <div class="r-line"><span>Tax</span><span><?= money($order['tax_amount']) ?></span></div>
        <?php endif; ?>
        <?php if ($order['service_amount'] > 0): ?>
            <div class="r-line"><span>Service Charge</span><span><?= money($order['service_amount']) ?></span></div>
        <?php endif; ?>
        <div class="r-total"><span>TOTAL</span><span><?= money($order['total_amount']) ?></span></div>

        <?php if (!empty($order['notes'])): ?>
            <div class="r-divider"></div>
            <div class="r-meta"><strong>Notes:</strong> <?= e($order['notes']) ?></div>
        <?php endif; ?>

        <div class="r-thanks">Thank you!<br>Please come again.</div>
    </div>

    <div class="r-actions">
        <a href="#" class="r-btn r-btn--print" onclick="window.print();return false;">🖨 Print</a>
        <a href="/menu/admin/orders.php?view=<?= (int) $order['id'] ?>" class="r-btn r-btn--back">Back</a>
    </div>

    <?php if ($autoPrint): ?>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
