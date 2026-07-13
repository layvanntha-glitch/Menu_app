<?php
/**
 * cart.php — the shopping cart.
 *
 * Two jobs in one file:
 *   A) Handle POST actions that CHANGE the cart (add / update / remove / clear),
 *      then redirect (Post/Redirect/Get pattern to avoid double-submits).
 *   B) On a normal GET, DISPLAY the current cart contents.
 *
 * The cart itself is just an array in the session:
 *   $_SESSION['cart'] = [ item_id => quantity, ... ]
 * We store only IDs and quantities; names/prices are always re-read from the
 * database so they are never stale or tampered with.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php'; // currency symbol

// Make sure the cart array exists.
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ---------------------------------------------------------------
// A) Handle actions (only on POST requests)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = (int) ($_POST['item_id'] ?? 0);

    switch ($action) {
        case 'add':
            // Confirm the item exists and is available before adding.
            $stmt = $pdo->prepare(
                'SELECT id FROM menu_items WHERE id = ? AND is_available = 1'
            );
            $stmt->execute([$itemId]);
            if ($stmt->fetch()) {
                $current = $_SESSION['cart'][$itemId] ?? 0;
                $_SESSION['cart'][$itemId] = $current + 1;
            }
            // Send the user back to the menu with a confirmation flag.
            redirect('/menu/index.php?added=1');
            break;

        case 'update':
            // Set an explicit quantity (from the cart page number input).
            $qty = (int) ($_POST['quantity'] ?? 1);
            if ($qty > 0) {
                $_SESSION['cart'][$itemId] = min($qty, 99);   // cap at 99
            } else {
                unset($_SESSION['cart'][$itemId]);            // 0 removes it
            }
            redirect('/menu/cart.php');
            break;

        case 'remove':
            unset($_SESSION['cart'][$itemId]);
            redirect('/menu/cart.php');
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            redirect('/menu/cart.php');
            break;
    }
}

// ---------------------------------------------------------------
// B) Build the data needed to display the cart
// ---------------------------------------------------------------
$cartItems = [];   // each: id, name, price, quantity, subtotal
$total = 0.0;

if (!empty($_SESSION['cart'])) {
    // Fetch details for exactly the item ids in the cart.
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, name, price, discount_type, discount_value FROM menu_items WHERE id IN ($placeholders)"
    );
    $stmt->execute($ids);

    foreach ($stmt as $row) {
        $qty = $_SESSION['cart'][$row['id']];
        $unit = effective_price($row);          // honour any active discount
        $subtotal = $unit * $qty;
        $total += $subtotal;
        $cartItems[] = [
            'id'        => $row['id'],
            'name'      => $row['name'],
            'price'     => $unit,
            'orig_price'=> (float) $row['price'],
            'quantity'  => $qty,
            'subtotal'  => $subtotal,
        ];
    }
}

$pageTitle = t('your_cart');
require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title"><?= t('your_cart') ?></h1>

<?php if (empty($cartItems)): ?>
    <div class="empty-state">
        <p class="empty-emoji">🛒</p>
        <h2><?= t('cart_empty') ?></h2>
        <p><?= t('cart_empty_sub') ?></p>
        <p style="margin-top:20px;">
            <a href="/menu/index.php" class="btn"><?= t('browse_menu') ?></a>
        </p>
    </div>
<?php else: ?>
    <table class="cart-table">
        <thead>
            <tr>
                <th><?= t('th_item') ?></th>
                <th><?= t('th_price') ?></th>
                <th><?= t('th_qty') ?></th>
                <th><?= t('th_subtotal') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cartItems as $ci): ?>
                <tr>
                    <td data-label="<?= t('th_item') ?>"><?= e($ci['name']) ?></td>
                    <td data-label="<?= t('th_price') ?>">
                        <?php if ($ci['price'] < $ci['orig_price'] - 0.001): ?>
                            <span class="price-old"><?= money($ci['orig_price']) ?></span>
                            <strong><?= money($ci['price']) ?></strong>
                        <?php else: ?>
                            <?= money($ci['price']) ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= t('th_qty') ?>">
                        <form method="post" action="/menu/cart.php" class="qty-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="item_id" value="<?= (int) $ci['id'] ?>">
                            <input type="number" name="quantity" value="<?= (int) $ci['quantity'] ?>"
                                   min="0" max="99" class="qty-input">
                            <button type="submit" class="btn btn--sm btn--muted"><?= t('update') ?></button>
                        </form>
                    </td>
                    <td data-label="<?= t('th_subtotal') ?>"><?= money($ci['subtotal']) ?></td>
                    <td>
                        <form method="post" action="/menu/cart.php">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_id" value="<?= (int) $ci['id'] ?>">
                            <button type="submit" class="btn btn--sm btn--danger"><?= t('remove') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="cart-total-label"><?= t('total') ?></td>
                <td colspan="2" class="cart-total-value"><?= money($total) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="cart-actions">
        <div>
            <a href="/menu/index.php" class="btn btn--ghost"><?= t('continue_shop') ?></a>
            <form method="post" action="/menu/cart.php" style="display:inline;">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn--muted"><?= t('clear_cart') ?></button>
            </form>
        </div>
        <a href="/menu/checkout.php" class="btn"><?= t('to_checkout') ?></a>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
