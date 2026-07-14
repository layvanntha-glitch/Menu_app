<?php
/**
 * includes/telegram_notify.php — push order notifications to a customer's
 * Telegram chat from the WEB side (Mini App checkout, admin status updates).
 *
 * How the customer is identified:
 *   When the storefront is opened as a Telegram Mini App, Telegram provides a
 *   signed `initData` string. The checkout form sends it to the server, we
 *   VALIDATE its signature with the bot token (so it can't be forged), and
 *   extract the Telegram user id (= their private chat id). We store that id
 *   on the order so the bot can message them about every status change.
 *
 * All functions are best-effort: if Telegram is unreachable or no chat id is
 * known, they simply do nothing — they never break checkout or the admin panel.
 */

/** The bot token (same one the polling bot uses), or null if not configured. */
function tg_bot_token(): ?string
{
    $t = getenv('TELEGRAM_BOT_TOKEN') ?: '';
    if ($t === '') {
        $file = __DIR__ . '/../telegram/.token';
        if (is_file($file)) {
            $t = trim((string) file_get_contents($file));
        }
    }
    return $t !== '' ? $t : null;
}

/**
 * A CA bundle path for cURL, or '' to leave CURLOPT_CAINFO unset so cURL uses
 * the system CA store. Prefers php.ini's curl.cainfo, then XAMPP's Windows
 * bundle if present. On Linux hosts (e.g. Railway) this returns '' and the
 * system certificates are used automatically.
 */
function tg_ca_bundle(): string
{
    $ca = ini_get('curl.cainfo');
    if ($ca && is_file($ca)) {
        return $ca;
    }
    $win = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
    return is_file($win) ? $win : '';
}

/**
 * Validate Telegram Mini App initData and return the user array (id, name, …)
 * or null if it is missing/invalid. Implements Telegram's documented check.
 */
function tg_validate_init_data(string $initData, ?string $token = null): ?array
{
    $token = $token ?? tg_bot_token();
    if (!$token || $initData === '') {
        return null;
    }

    parse_str($initData, $data);
    if (empty($data['hash']) || empty($data['user'])) {
        return null;
    }
    $hash = $data['hash'];
    unset($data['hash']);

    // Build the data-check-string: "key=value" pairs sorted by key, LF-joined.
    ksort($data);
    $pairs = [];
    foreach ($data as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    $checkString = implode("\n", $pairs);

    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $calc   = hash_hmac('sha256', $checkString, $secret);

    if (!hash_equals($calc, $hash)) {
        return null;   // forged or corrupted
    }

    $user = json_decode($data['user'], true);
    return is_array($user) && isset($user['id']) ? $user : null;
}

/**
 * The public https base URL of the app (ending in a slash, e.g.
 * "https://xxxx.trycloudflare.com/menu/"), used to build links the customer can
 * open from Telegram (invoice, etc.). Reads env TASTY_PUBLIC_URL or the
 * telegram/.miniapp_url file. Returns null if none is configured.
 */
function tg_public_base(): ?string
{
    $u = getenv('TASTY_PUBLIC_URL') ?: '';
    if ($u === '') {
        $f = __DIR__ . '/../telegram/.miniapp_url';
        if (is_file($f)) {
            $u = trim((string) file_get_contents($f));
        }
    }
    if ($u === '') {
        return null;
    }
    // Strip a trailing "index.php" (or any .php entry) to get the app base dir.
    $u = preg_replace('#/[^/]*\.php(\?.*)?$#', '/', $u);
    if (strncmp($u, 'https://', 8) !== 0) {
        return null;   // Telegram URL buttons require https
    }
    return rtrim($u, '/') . '/';
}

/**
 * Send a message to a Telegram chat. Returns true on success.
 * $replyMarkup (optional) is an inline_keyboard array for buttons.
 */
function tg_send(string $chatId, string $text, ?array $replyMarkup = null): bool
{
    $token = tg_bot_token();
    if (!$token || $chatId === '') {
        return false;
    }
    $ca = tg_ca_bundle();

    $fields = [
        'chat_id' => $chatId,
        'text'    => $text,
    ];
    if ($replyMarkup !== null) {
        $fields['reply_markup'] = json_encode(['inline_keyboard' => $replyMarkup]);
    }

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_TIMEOUT        => 8,
    ];
    if ($ca !== '') { $opts[CURLOPT_CAINFO] = $ca; }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    curl_close($ch);
    if ($raw === false) {
        return false;
    }
    $resp = json_decode($raw, true);
    return (bool) ($resp['ok'] ?? false);
}

/** Human label + emoji for each status. */
function tg_status_label(string $status): array
{
    $map = [
        'pending'    => ['⏳', 'Pending',   'We’ve received your order and it’s awaiting confirmation.'],
        'preparing'  => ['👨‍🍳', 'Preparing', 'Good news — the kitchen is now preparing your food.'],
        'ready'      => ['✅', 'Ready',     'Your order is ready! Please collect it / it’s on the way to your table.'],
        'completed'  => ['🎉', 'Completed', 'Order completed — enjoy your meal! Thank you for ordering with us.'],
        'cancelled'  => ['❌', 'Cancelled', 'Your order has been cancelled. Contact us if this is unexpected.'],
    ];
    return $map[$status] ?? ['🔔', ucfirst($status), 'Your order status was updated.'];
}

/** "Dine In (Table 5)" / "Takeaway" from an order row. */
function tg_order_type_label(array $order): string
{
    return ($order['order_type'] ?? '') === 'dine_in'
        ? 'Dine In' . (!empty($order['table_number']) ? " (Table {$order['table_number']})" : '')
        : 'Takeaway';
}

/**
 * The shared, detailed order block reused by every message: itemised lines,
 * order type, full price breakdown, timing, and any customer note.
 */
function tg_order_details_block(array $order, array $items, string $currency = '$'): string
{
    $money = fn($n) => $currency . number_format((float) $n, 2);

    $t = "🍽️ Items:\n";
    $count = 0;
    foreach ($items as $it) {
        $t .= "• {$it['quantity']}× {$it['item_name']} — " . $money($it['subtotal']) . "\n";
        $count += (int) $it['quantity'];
    }
    $t .= "\nType: " . tg_order_type_label($order);
    if (!empty($order['customer_name'])) $t .= "\nName: {$order['customer_name']}";
    if (!empty($order['phone']))         $t .= "\nPhone: {$order['phone']}";

    $t .= "\n\n💰 Payment:";
    if (isset($order['subtotal']))       $t .= "\nSubtotal: " . $money($order['subtotal']);
    if (($order['tax_amount'] ?? 0) > 0)     $t .= "\nTax: " . $money($order['tax_amount']);
    if (($order['service_amount'] ?? 0) > 0) $t .= "\nService: " . $money($order['service_amount']);
    $t .= "\nTotal: " . $money($order['total_amount'] ?? 0) . "  ({$count} item" . ($count === 1 ? '' : 's') . ")";

    if (!empty($order['notes'])) {
        $t .= "\n\n📝 Note: {$order['notes']}";
    }
    if (!empty($order['created_at'])) {
        $ts = strtotime($order['created_at']);
        if ($ts) $t .= "\n🕒 Placed: " . date('M j, Y g:i A', $ts);
    }
    return $t;
}

/** An inline "View / Download Invoice" button + plain link, or [null, ''] if no public URL. */
function tg_invoice_extras(int $orderId): array
{
    $base = tg_public_base();
    if (!$base) {
        return [null, ''];
    }
    $url = $base . 'invoice.php?id=' . $orderId;
    $markup = [[['text' => '🧾 View / Download Invoice', 'url' => $url]]];
    return [$markup, "\n\n🧾 Invoice: " . $url];
}

/** Notify the customer that their order was received (with a full summary). */
function tg_notify_new_order(string $chatId, array $order, array $items, string $currency = '$'): bool
{
    [$emoji, $label, $line] = tg_status_label('pending');

    $text  = "🧾 Order #{$order['id']} received!\n";
    $text .= "Status: {$label} {$emoji}\n\n";
    $text .= tg_order_details_block($order, $items, $currency);
    $text .= "\n\nWe'll message you at every step. Thank you! 🍽️";

    [$markup, $linkLine] = tg_invoice_extras((int) $order['id']);
    $text .= $linkLine;

    return tg_send($chatId, $text, $markup);
}

/**
 * Alert the kitchen (chef) about a brand-new order, if a kitchen chat id is
 * configured (Settings → Kitchen Telegram Chat ID). Best-effort.
 */
function tg_notify_kitchen(string $kitchenChatId, array $order, array $items, string $currency = '$'): bool
{
    if ($kitchenChatId === '') {
        return false;
    }
    $text  = "👨‍🍳 NEW ORDER #{$order['id']}  (" . tg_order_type_label($order) . ")\n\n";
    $text .= tg_order_details_block($order, $items, $currency);
    $text .= "\n\n▶ Open the Kitchen Display to start cooking.";

    return tg_send($kitchenChatId, $text);
}

/**
 * Notify the customer that their order's status changed. Fetches the full order
 * + items so the message carries complete detail. Best-effort.
 */
function tg_notify_status(PDO $pdo, string $chatId, int $orderId, string $status, string $currency = '$'): bool
{
    [$emoji, $label, $line] = tg_status_label($status);

    $text = "🔔 Order #{$orderId} update\n"
          . "Status: {$label} {$emoji}\n{$line}\n\n";

    // Pull the full order + items for a detailed recap.
    $ord = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $ord->execute([$orderId]);
    $order = $ord->fetch(PDO::FETCH_ASSOC);

    $markup = null;
    if ($order) {
        $li = $pdo->prepare('SELECT item_name, quantity, subtotal FROM order_items WHERE order_id = ?');
        $li->execute([$orderId]);
        $items = $li->fetchAll(PDO::FETCH_ASSOC);
        $text .= tg_order_details_block($order, $items, $currency);

        // Offer the invoice where it's most useful.
        if (in_array($status, ['ready', 'completed'], true)) {
            [$markup, $linkLine] = tg_invoice_extras($orderId);
            $text .= $linkLine;
        }
    }

    return tg_send($chatId, $text, $markup);
}
