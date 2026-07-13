<?php
/**
 * invoice.php — customer invoice for a completed order.
 *
 *   invoice.php?id=N          -> a clean, print-ready HTML invoice
 *   invoice.php?id=N&pdf=1    -> downloads the same invoice as a real PDF file
 *
 * The customer can hand the printout to staff or keep the PDF as a receipt.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo 'Invoice not found. <a href="/menu/index.php">Back to menu</a>';
    exit;
}

$lineStmt = $pdo->prepare('SELECT item_name, price, quantity, subtotal FROM order_items WHERE order_id = ?');
$lineStmt->execute([$id]);
$items = $lineStmt->fetchAll();

$restaurant = setting('restaurant_name', 'Tasty Bites');
$symbol     = setting('currency_symbol', '$');
$invoiceNo  = 'INV-' . date('Y', strtotime($order['created_at'])) . '-' . str_pad((string) $order['id'], 4, '0', STR_PAD_LEFT);
$fmt = fn($n) => $symbol . number_format((float) $n, 2);

// ============================================================
//  PDF branch — stream a downloadable file
// ============================================================
if (isset($_GET['pdf'])) {
    require_once __DIR__ . '/includes/pdf.php';
    $pdf = new SimplePDF();
    $pdf->addPage();
    $W = $pdf->width();
    $primary = [0.91, 0.33, 0.18];   // brand orange
    $muted   = [0.42, 0.42, 0.42];

    // Header band
    $pdf->rect(0, 0, $W, 96, $primary);
    $pdf->textColor(40, 46, $restaurant, [1, 1, 1], 22, true);
    $pdf->textColor(40, 68, 'Order Receipt / Invoice', [1, 1, 1], 11);
    $pdf->textColor($W - 40 - (strlen('INVOICE') * 22 * 0.5), 52, 'INVOICE', [1, 1, 1], 22, true);

    // Meta
    $y = 130;
    $pdf->text(40, $y, 'Invoice No:', 10, true);   $pdf->text(120, $y, $invoiceNo, 10);
    $pdf->textRight($W - 40, $y, 'Date: ' . date('M j, Y H:i', strtotime($order['created_at'])), 10);
    $y += 18;
    $pdf->text(40, $y, 'Status:', 10, true);        $pdf->text(120, $y, ucfirst($order['status']), 10);
    $type = $order['order_type'] === 'dine_in' ? 'Dine In' . ($order['table_number'] ? ' (Table ' . $order['table_number'] . ')' : '') : 'Takeaway';
    $pdf->textRight($W - 40, $y, 'Type: ' . $type, 10);

    // Bill to
    $y += 34;
    $pdf->textColor(40, $y, 'BILL TO', $muted, 9, true);
    $y += 16;
    $pdf->text(40, $y, $order['customer_name'], 12, true);
    if (!empty($order['phone'])) { $y += 15; $pdf->text(40, $y, 'Phone: ' . $order['phone'], 10); }

    // Items table header
    $y += 34;
    $colQty = 330; $colPrice = 445; $colAmt = $W - 40;
    $pdf->rect(40, $y - 12, $W - 80, 22, [0.96, 0.95, 0.93]);
    $pdf->text(48, $y + 3, 'ITEM', 9, true);
    $pdf->textRight($colQty, $y + 3, 'QTY', 9, true);
    $pdf->textRight($colPrice, $y + 3, 'PRICE', 9, true);
    $pdf->textRight($colAmt, $y + 3, 'AMOUNT', 9, true);
    $y += 24;

    foreach ($items as $it) {
        $pdf->text(48, $y, $it['item_name'], 10);
        $pdf->textRight($colQty, $y, (string) (int) $it['quantity'], 10);
        $pdf->textRight($colPrice, $y, $fmt($it['price']), 10);
        $pdf->textRight($colAmt, $y, $fmt($it['subtotal']), 10);
        $y += 18;
        $pdf->line(40, $y - 6, $W - 40, $y - 6, 0.3, 0.85);
    }

    // Totals
    $y += 12;
    $labelX = $colPrice;
    $pdf->textRight($labelX, $y, 'Subtotal', 10);          $pdf->textRight($colAmt, $y, $fmt($order['subtotal']), 10);
    if ($order['tax_amount'] > 0)     { $y += 16; $pdf->textRight($labelX, $y, 'Tax', 10);            $pdf->textRight($colAmt, $y, $fmt($order['tax_amount']), 10); }
    if ($order['service_amount'] > 0) { $y += 16; $pdf->textRight($labelX, $y, 'Service Charge', 10); $pdf->textRight($colAmt, $y, $fmt($order['service_amount']), 10); }
    $y += 22;
    $pdf->line(300, $y - 12, $W - 40, $y - 12, 0.8, 0.2);
    $pdf->textColor($labelX, $y, 'TOTAL', $primary, 13, true);
    $pdf->textColor($colAmt - (strlen($fmt($order['total_amount'])) * 13 * 0.5), $y, $fmt($order['total_amount']), $primary, 13, true);

    // Footer
    $pdf->textColor(40, $pdf->height() - 60, 'Thank you for your order! — ' . $restaurant, $muted, 10);
    if (!empty($order['notes'])) {
        $pdf->text(40, $pdf->height() - 40, 'Notes: ' . $order['notes'], 9);
    }

    $data = $pdf->output();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $invoiceNo . '.pdf"');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

// ============================================================
//  HTML branch — printable invoice
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= e($invoiceNo) ?> — <?= e($restaurant) ?></title>
    <style>
        :root { --primary: #e8552d; --ink: #201b18; --muted: #6b7280; --border: #e5e1da; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #eef0f2; color: var(--ink); padding: 24px; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,.12); }
        .inv-head { background: linear-gradient(135deg, #e8552d, #f2a83a); color: #fff; padding: 28px 32px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .inv-head h1 { font-size: 1.5rem; letter-spacing: -.02em; }
        .inv-head .sub { opacity: .9; font-size: .9rem; margin-top: 2px; }
        .inv-head .big { font-size: 1.8rem; font-weight: 800; letter-spacing: .04em; }
        .inv-body { padding: 28px 32px; }
        .meta { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; font-size: .9rem; margin-bottom: 22px; }
        .meta .label { color: var(--muted); }
        .billto { margin-bottom: 22px; }
        .billto .label { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
        .billto .name { font-weight: 700; font-size: 1.05rem; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        thead th { background: #f6f4f1; text-align: left; padding: 10px 12px; font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
        th.num, td.num { text-align: right; }
        tbody td { padding: 11px 12px; border-bottom: 1px solid var(--border); }
        .totals { margin-top: 16px; margin-left: auto; width: 280px; font-size: .92rem; }
        .totals .row { display: flex; justify-content: space-between; padding: 5px 0; color: var(--muted); }
        .totals .grand { display: flex; justify-content: space-between; padding-top: 12px; margin-top: 6px; border-top: 2px solid #ddd; font-weight: 800; font-size: 1.2rem; color: var(--primary); }
        .notes { margin-top: 22px; font-size: .85rem; color: var(--muted); }
        .thanks { text-align: center; margin-top: 26px; color: var(--muted); font-size: .9rem; }
        .actions { max-width: 760px; margin: 18px auto 0; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 12px 22px; border: none; border-radius: 999px; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; }
        .btn--print { background: var(--primary); color: #fff; }
        .btn--pdf { background: #1f2430; color: #fff; }
        .btn--back { background: #e5e7eb; color: #333; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border-radius: 0; max-width: 100%; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="inv-head">
            <div>
                <h1>🍽️ <?= e($restaurant) ?></h1>
                <div class="sub">Order Receipt / Invoice</div>
            </div>
            <div style="text-align:right;">
                <div class="big">INVOICE</div>
                <div class="sub"><?= e($invoiceNo) ?></div>
            </div>
        </div>

        <div class="inv-body">
            <div class="meta">
                <div>
                    <div><span class="label">Date:</span> <?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></div>
                    <div><span class="label">Status:</span> <?= e(ucfirst($order['status'])) ?></div>
                </div>
                <div style="text-align:right;">
                    <div><span class="label">Type:</span>
                        <?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?>
                        <?php if ($order['order_type'] === 'dine_in' && $order['table_number']): ?>
                            (Table <?= e($order['table_number']) ?>)
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($order['phone'])): ?><div><span class="label">Phone:</span> <?= e($order['phone']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="billto">
                <div class="label">Bill To</div>
                <div class="name"><?= e($order['customer_name']) ?></div>
            </div>

            <table>
                <thead>
                    <tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['item_name']) ?></td>
                            <td class="num"><?= (int) $it['quantity'] ?></td>
                            <td class="num"><?= e($fmt($it['price'])) ?></td>
                            <td class="num"><?= e($fmt($it['subtotal'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <div class="row"><span>Subtotal</span><span><?= e($fmt($order['subtotal'])) ?></span></div>
                <?php if ($order['tax_amount'] > 0): ?><div class="row"><span>Tax</span><span><?= e($fmt($order['tax_amount'])) ?></span></div><?php endif; ?>
                <?php if ($order['service_amount'] > 0): ?><div class="row"><span>Service Charge</span><span><?= e($fmt($order['service_amount'])) ?></span></div><?php endif; ?>
                <div class="grand"><span>Total</span><span><?= e($fmt($order['total_amount'])) ?></span></div>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <p class="notes"><strong>Notes:</strong> <?= e($order['notes']) ?></p>
            <?php endif; ?>

            <p class="thanks">Thank you for your order! We hope to see you again. 🍴</p>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn--print" onclick="window.print()">🖨 Print</button>
        <a class="btn btn--pdf" href="/menu/invoice.php?id=<?= (int) $order['id'] ?>&pdf=1">⬇ Download PDF</a>
        <a class="btn btn--back" href="/menu/index.php">← Back to menu</a>
    </div>
</body>
</html>
