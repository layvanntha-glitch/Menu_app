<?php
/**
 * admin/kitchen.php — the Kitchen Display (KDS).
 *
 * A live board for the chef: incoming orders on the left, cooking in the
 * middle, ready on the right. Tapping an action advances the order AND
 * messages the customer on Telegram ("being prepared" -> "ready!").
 * Accessible to both chefs and admins. The board auto-refreshes.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/telegram_notify.php';
require_admin();   // any signed-in staff (admin or chef)

// Allowed forward transitions from the board.
$NEXT = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'];

// -------- Handle an action (advance / cancel), then redirect (PRG) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $to = $_POST['to'] ?? '';

    if ($id > 0 && in_array($to, ['preparing', 'ready', 'completed', 'cancelled'], true)) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$to, $id]);

        // Tell the customer on Telegram (best-effort).
        $q = $pdo->prepare('SELECT tg_chat_id FROM orders WHERE id = ?');
        $q->execute([$id]);
        $chat = $q->fetchColumn();
        if ($chat) {
            tg_notify_status($pdo, (string) $chat, $id, $to, setting('currency_symbol', '$'));
        }
    }
    redirect('/menu/admin/kitchen.php');
}

// -------- Load active orders + their items --------
$orders = $pdo->query(
    "SELECT * FROM orders
     WHERE status IN ('pending','preparing','ready')
     ORDER BY created_at ASC"
)->fetchAll();

$itemStmt = $pdo->prepare('SELECT item_name, quantity FROM order_items WHERE order_id = ?');
$columns = ['pending' => [], 'preparing' => [], 'ready' => []];
foreach ($orders as $o) {
    $itemStmt->execute([$o['id']]);
    $o['items'] = $itemStmt->fetchAll();
    $columns[$o['status']][] = $o;
}

$colMeta = [
    'pending'   => ['title' => '🆕 New Orders',  'next' => 'preparing', 'btn' => '👨‍🍳 Start Cooking'],
    'preparing' => ['title' => '👨‍🍳 Cooking',    'next' => 'ready',     'btn' => '✅ Food Ready'],
    'ready'     => ['title' => '✅ Ready',        'next' => 'completed', 'btn' => '📦 Picked Up'],
];

$pageTitle = 'Kitchen';
$activeNav = 'kitchen';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
    <h1 class="admin-h1" style="margin:0;">👨‍🍳 Kitchen Display</h1>
    <span class="muted" id="refreshNote">Auto-refreshing every 15s · <span id="count"><?= count($orders) ?></span> active</span>
</div>

<div class="kds-board">
    <?php foreach ($colMeta as $status => $meta): ?>
        <section class="kds-col kds-col--<?= $status ?>">
            <h2 class="kds-col__title"><?= $meta['title'] ?> <span class="kds-badge"><?= count($columns[$status]) ?></span></h2>

            <?php if (empty($columns[$status])): ?>
                <p class="kds-empty">—</p>
            <?php endif; ?>

            <?php foreach ($columns[$status] as $o): ?>
                <?php $waitMin = max(0, (int) floor((time() - strtotime($o['created_at'])) / 60)); ?>
                <article class="kds-card <?= $waitMin >= 15 ? 'kds-card--late' : '' ?>">
                    <div class="kds-card__head">
                        <span class="kds-order">#<?= (int) $o['id'] ?></span>
                        <span class="kds-type">
                            <?= $o['order_type'] === 'dine_in' ? '🍽 Table ' . e($o['table_number'] ?: '—') : '🥡 Takeaway' ?>
                        </span>
                        <span class="kds-wait" title="minutes since ordered"><?= $waitMin ?>m</span>
                    </div>
                    <div class="kds-card__name"><?= e($o['customer_name']) ?><?= $o['tg_chat_id'] ? ' · 📲' : '' ?></div>
                    <ul class="kds-items">
                        <?php foreach ($o['items'] as $it): ?>
                            <li><span class="kds-qty"><?= (int) $it['quantity'] ?>×</span> <?= e($it['item_name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($o['notes'])): ?>
                        <p class="kds-note">📝 <?= e($o['notes']) ?></p>
                    <?php endif; ?>
                    <div class="kds-actions">
                        <form method="post" action="/menu/admin/kitchen.php">
                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                            <input type="hidden" name="to" value="<?= e($meta['next']) ?>">
                            <button class="btn btn--block kds-advance"><?= $meta['btn'] ?></button>
                        </form>
                        <?php if ($status !== 'ready'): ?>
                            <form method="post" action="/menu/admin/kitchen.php"
                                  onsubmit="return confirm('Cancel order #<?= (int) $o['id'] ?>?');">
                                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="to" value="cancelled">
                                <button class="btn btn--sm btn--muted kds-cancel">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<script>
    // Auto-refresh the board (paused briefly after interaction so a tap isn't lost).
    var kdsTimer = setTimeout(function () { location.reload(); }, 15000);
    document.addEventListener('submit', function () { clearTimeout(kdsTimer); });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
