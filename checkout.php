<?php
/**
 * checkout.php — turn the session cart into a saved order.
 *
 * GET  : show the checkout form (customer details) + order summary.
 * POST : validate input, then save the order to the database inside a
 *        transaction, clear the cart, and redirect to a confirmation page.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth_user.php';
require_once __DIR__ . '/includes/telegram_notify.php';

// If the cart is empty there is nothing to check out.
if (empty($_SESSION['cart'])) {
    redirect('/menu/cart.php');
}

// Re-read the cart items from the DB (never trust prices from the session).
$ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT id, name, price, discount_type, discount_value FROM menu_items WHERE id IN ($placeholders)"
);
$stmt->execute($ids);

$cartItems = [];
$itemsSubtotal = 0.0;
foreach ($stmt as $row) {
    $qty = (int) $_SESSION['cart'][$row['id']];
    $unit = effective_price($row);          // honour any active discount
    $subtotal = $unit * $qty;
    $itemsSubtotal += $subtotal;
    $cartItems[] = [
        'id'       => $row['id'],
        'name'     => $row['name'],
        'price'    => $unit,
        'quantity' => $qty,
        'subtotal' => $subtotal,
    ];
}

// Compute tax + service charge + grand total from configured rates.
$totals = compute_totals($itemsSubtotal);

$errors = [];
// Keep submitted values so the form can be re-filled if validation fails.
$form = [
    'customer_name' => '',
    'phone'         => '',
    'order_type'    => 'dine_in',
    'table_number'  => '',
    'notes'         => '',
];

// Prefill the form from the logged-in customer's account (if any).
$account = current_user();
if ($account && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $form['customer_name'] = $account['name'];
    $form['phone']         = $account['phone'] ?? '';
}

// ---------------------------------------------------------------
// Handle submission
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect + trim input.
    foreach ($form as $key => $_) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    // --- Validate ---
    if ($form['customer_name'] === '') {
        $errors[] = t('err_name');
    }
    if (!in_array($form['order_type'], ['dine_in', 'takeaway'], true)) {
        $errors[] = t('err_type');
    }
    if ($form['order_type'] === 'dine_in' && $form['table_number'] === '') {
        $errors[] = t('err_table');
    }

    // If the checkout happened inside the Telegram Mini App, capture (and
    // cryptographically verify) the customer's Telegram id so the bot can
    // notify them about this order.
    $tgChatId = null;
    $tgUser = tg_validate_init_data($_POST['tg_init_data'] ?? '');
    if ($tgUser) {
        $tgChatId = (string) $tgUser['id'];
    } elseif (!empty($_SESSION['tg_chat_id'])) {
        // Fallback: captured earlier when the Mini App first loaded (tg_link.php).
        $tgChatId = (string) $_SESSION['tg_chat_id'];
    } elseif ($account) {
        // Fallback: this customer linked Telegram on a previous order, so we can
        // still notify them even when ordering from the normal website. Read it
        // fresh from the DB (the session copy may predate the tg_chat_id column).
        $saved = $pdo->prepare('SELECT tg_chat_id FROM users WHERE id = ?');
        $saved->execute([$account['id']]);
        $savedId = (string) $saved->fetchColumn();
        if ($savedId !== '') {
            $tgChatId = $savedId;
        }
    }

    // If we know both the account and a fresh chat id, remember it for next time.
    if ($account && $tgChatId) {
        $pdo->prepare('UPDATE users SET tg_chat_id = ? WHERE id = ? AND (tg_chat_id IS NULL OR tg_chat_id <> ?)')
            ->execute([$tgChatId, $account['id'], $tgChatId]);
    }

    // --- Save if valid ---
    if (empty($errors)) {
        try {
            // A transaction ensures the order header AND all its line items
            // are saved together, or not at all.
            $pdo->beginTransaction();

            // 1) Insert the order "header" with the full price breakdown.
            //    Link it to the logged-in customer account (null for guests).
            $orderStmt = $pdo->prepare(
                'INSERT INTO orders
                    (user_id, tg_chat_id, customer_name, phone, order_type, table_number,
                     subtotal, tax_amount, service_amount, total_amount, notes)
                 VALUES (:uid, :tg, :name, :phone, :type, :table,
                     :subtotal, :tax, :service, :total, :notes)'
            );
            $orderStmt->execute([
                ':uid'      => $account['id'] ?? null,
                ':tg'       => $tgChatId,
                ':name'     => $form['customer_name'],
                ':phone'    => $form['phone'] !== '' ? $form['phone'] : null,
                ':type'     => $form['order_type'],
                ':table'    => $form['order_type'] === 'dine_in' ? $form['table_number'] : null,
                ':subtotal' => $totals['subtotal'],
                ':tax'      => $totals['tax'],
                ':service'  => $totals['service'],
                ':total'    => $totals['total'],
                ':notes'    => $form['notes'] !== '' ? $form['notes'] : null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // 2) Insert each line item (with snapshotted name + price).
            $lineStmt = $pdo->prepare(
                'INSERT INTO order_items
                    (order_id, menu_item_id, item_name, price, quantity, subtotal)
                 VALUES (:oid, :mid, :name, :price, :qty, :sub)'
            );
            foreach ($cartItems as $ci) {
                $lineStmt->execute([
                    ':oid'   => $orderId,
                    ':mid'   => $ci['id'],
                    ':name'  => $ci['name'],
                    ':price' => $ci['price'],
                    ':qty'   => $ci['quantity'],
                    ':sub'   => $ci['subtotal'],
                ]);
            }

            $pdo->commit();

            // Push a Telegram confirmation to the customer (best-effort).
            if ($tgChatId) {
                $msgItems = array_map(fn($ci) => [
                    'quantity'  => $ci['quantity'],
                    'item_name' => $ci['name'],
                    'subtotal'  => $ci['subtotal'],
                ], $cartItems);
                $orderForMsg = [
                    'id'             => $orderId,
                    'subtotal'       => $totals['subtotal'],
                    'tax_amount'     => $totals['tax'],
                    'service_amount' => $totals['service'],
                    'total_amount'   => $totals['total'],
                    'order_type'     => $form['order_type'],
                    'table_number'   => $form['order_type'] === 'dine_in' ? $form['table_number'] : null,
                ];
                tg_notify_new_order($tgChatId, $orderForMsg, $msgItems, setting('currency_symbol', '$'));
            }

            // Alert the kitchen/chef on Telegram about the new order (if configured).
            $kitchenChat = (string) setting('kitchen_chat_id', '');
            if ($kitchenChat !== '') {
                $msgItems = $msgItems ?? array_map(fn($ci) => [
                    'quantity'  => $ci['quantity'],
                    'item_name' => $ci['name'],
                    'subtotal'  => $ci['subtotal'],
                ], $cartItems);
                $orderForKitchen = [
                    'id'           => $orderId,
                    'order_type'   => $form['order_type'],
                    'table_number' => $form['order_type'] === 'dine_in' ? $form['table_number'] : null,
                    'total_amount' => $totals['total'],
                    'notes'        => $form['notes'],
                ];
                tg_notify_kitchen($kitchenChat, $orderForKitchen, $msgItems, setting('currency_symbol', '$'));
            }

            // Empty the cart and go to the confirmation page.
            $_SESSION['cart'] = [];
            redirect('/menu/order_confirmation.php?id=' . $orderId);

        } catch (Throwable $e) {
            $pdo->rollBack();   // undo any partial insert
            $errors[] = t('err_save');
        }
    }
}

$pageTitle = t('checkout');
require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title"><?= t('checkout') ?></h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert--error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="checkout-layout">
    <!-- Left: customer details form -->
    <form method="post" action="/menu/checkout.php" class="checkout-form card">
        <h2 class="card-title"><?= t('your_details') ?></h2>
        <!-- Signed Telegram Mini App data (only filled when opened inside Telegram) -->
        <input type="hidden" name="tg_init_data" id="tg_init_data">
        <?php if ($account): ?>
            <p class="muted" style="margin:-6px 0 14px;font-size:.85rem;"><?= t('ordering_as') ?> <strong><?= e($account['name']) ?></strong>.</p>
        <?php endif; ?>

        <label class="field">
            <span><?= t('name') ?> <span class="req">*</span></span>
            <input type="text" name="customer_name" value="<?= e($form['customer_name']) ?>" required>
        </label>

        <label class="field">
            <span><?= t('phone') ?></span>
            <input type="text" name="phone" value="<?= e($form['phone']) ?>">
        </label>

        <label class="field">
            <span><?= t('order_type') ?> <span class="req">*</span></span>
            <select name="order_type" id="order_type">
                <option value="dine_in"  <?= $form['order_type'] === 'dine_in'  ? 'selected' : '' ?>><?= t('dine_in') ?></option>
                <option value="takeaway" <?= $form['order_type'] === 'takeaway' ? 'selected' : '' ?>><?= t('takeaway') ?></option>
            </select>
        </label>

        <label class="field" id="table_field">
            <span><?= t('table_number') ?></span>
            <input type="text" name="table_number" value="<?= e($form['table_number']) ?>">
        </label>

        <label class="field">
            <span><?= t('notes_opt') ?></span>
            <textarea name="notes" rows="3"><?= e($form['notes']) ?></textarea>
        </label>

        <button type="submit" class="btn btn--block"><?= t('place_order') ?></button>
    </form>

    <!-- Right: order summary -->
    <aside class="order-summary card">
        <h2 class="card-title"><?= t('order_summary') ?></h2>
        <ul class="summary-list">
            <?php foreach ($cartItems as $ci): ?>
                <li>
                    <span><?= (int) $ci['quantity'] ?>× <?= e($ci['name']) ?></span>
                    <span><?= money($ci['subtotal']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="summary-breakdown">
            <div><span><?= t('th_subtotal') ?></span><span><?= money($totals['subtotal']) ?></span></div>
            <?php if ($totals['tax'] > 0): ?>
                <div><span><?= t('tax') ?> (<?= rtrim(rtrim(number_format($totals['tax_rate'], 2), '0'), '.') ?>%)</span><span><?= money($totals['tax']) ?></span></div>
            <?php endif; ?>
            <?php if ($totals['service'] > 0): ?>
                <div><span><?= t('service') ?> (<?= rtrim(rtrim(number_format($totals['service_rate'], 2), '0'), '.') ?>%)</span><span><?= money($totals['service']) ?></span></div>
            <?php endif; ?>
        </div>
        <div class="summary-total">
            <span><?= t('total') ?></span>
            <span><?= money($totals['total']) ?></span>
        </div>
    </aside>
</div>

<script>
    // Show the "Table Number" field only for dine-in orders.
    (function () {
        var type = document.getElementById('order_type');
        var tableField = document.getElementById('table_field');
        function sync() {
            tableField.style.display = (type.value === 'dine_in') ? '' : 'none';
        }
        type.addEventListener('change', sync);
        sync();
    })();

    // Inside Telegram, attach the signed initData so the bot can notify the user.
    (function () {
        var wa = window.Telegram && window.Telegram.WebApp;
        if (wa && wa.initData) {
            document.getElementById('tg_init_data').value = wa.initData;
        }
    })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
