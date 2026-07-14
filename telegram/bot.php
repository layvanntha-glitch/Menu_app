<?php
/**
 * telegram/bot.php — Tasty Bites ordering bot (long polling).
 *
 * Lets customers browse the menu and place orders straight from Telegram.
 * Orders are written into the SAME SQLite database as the website, so they
 * appear in the admin panel (Orders) exactly like web orders.
 *
 * WHY LONG POLLING?  This script calls OUT to Telegram's API, so it works
 * from localhost with no public URL, no HTTPS certificate and no ngrok.
 *
 * -------- How to run / test --------
 *   1. In Telegram, message @BotFather -> /newbot -> copy the token.
 *   2. Give this script the token (either one works):
 *        - set an env var:   set TELEGRAM_BOT_TOKEN=123456:ABC...      (Windows CMD)
 *                            $env:TELEGRAM_BOT_TOKEN="123456:ABC..."   (PowerShell)
 *        - or create a file: telegram/.token  containing just the token
 *   3. Start it from the CLI:   C:\xampp\php\php.exe telegram\bot.php
 *   4. Open your bot in Telegram and send /start.
 *   5. Place an order — then check http://localhost/menu/admin/orders.php
 *
 * Stop the bot with Ctrl+C.
 */

date_default_timezone_set('Asia/Phnom_Penh');

require_once __DIR__ . '/../config/db.php';        // $pdo (SQLite)
require_once __DIR__ . '/../includes/settings.php'; // setting(), compute_totals()

// ---------- Token ----------
$TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';
if ($TOKEN === '' && is_file(__DIR__ . '/.token')) {
    $TOKEN = trim((string) file_get_contents(__DIR__ . '/.token'));
}
if ($TOKEN === '') {
    fwrite(STDERR,
        "No bot token found.\n" .
        "Set TELEGRAM_BOT_TOKEN, or put the token in telegram/.token, then re-run.\n" .
        "Get a token from @BotFather in Telegram (/newbot).\n");
    exit(1);
}

define('API', 'https://api.telegram.org/bot' . $TOKEN . '/');
// Use the configured/system CA store. Only fall back to XAMPP's Windows cert
// bundle if it actually exists — on Linux hosts (e.g. Railway) cURL uses the
// system CA store automatically when CURLOPT_CAINFO is left unset.
$WIN_CA = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
$CA = ini_get('curl.cainfo') ?: (is_file($WIN_CA) ? $WIN_CA : '');

// ---------- Optional Mini App (Web App) URL ----------
// Telegram Mini Apps must be served over PUBLIC HTTPS — http://localhost will
// NOT open on a phone (and Telegram rejects it). To enable the "Open Menu" app
// button, expose the site publicly over HTTPS (e.g. a cloudflared/ngrok tunnel
// to http://localhost/menu/) and put that https URL here:
//   - env var:  TASTY_MINIAPP_URL=https://xxxx.trycloudflare.com/menu/
//   - or file:  telegram/.miniapp_url  containing just the URL
$MINIAPP = getenv('TASTY_MINIAPP_URL') ?: '';
if ($MINIAPP === '' && is_file(__DIR__ . '/.miniapp_url')) {
    $MINIAPP = trim((string) file_get_contents(__DIR__ . '/.miniapp_url'));
}
// Only accept a valid https URL (Telegram's requirement).
if (!preg_match('~^https://~i', $MINIAPP)) {
    $MINIAPP = '';
}

// ---------- In-memory per-chat state (persists while the bot runs) ----------
$CARTS  = [];   // chat_id => [item_id => qty]
$STATE  = [];   // chat_id => ['await' => 'table', 'order_type' => 'dine_in']

// ---------- Small helpers ----------
function money_fmt($n): string
{
    $sym = setting('currency_symbol', '$');
    return $sym . number_format((float) $n, 2);
}

/** Call any Telegram Bot API method. */
function tg(string $method, array $params = [])
{
    global $CA;
    $ch = curl_init(API . $method);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 65,
    ];
    if ($CA !== '') { $opts[CURLOPT_CAINFO] = $CA; }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        fwrite(STDERR, 'curl error: ' . curl_error($ch) . "\n");
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($raw, true);
}

function send(int $chatId, string $text, ?array $keyboard = null): void
{
    $params = ['chat_id' => $chatId, 'text' => $text];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    tg('sendMessage', $params);
}

function answer(string $callbackId, string $text = ''): void
{
    tg('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
}

// ---------- Screens ----------
function screen_welcome(int $chatId): void
{
    global $MINIAPP;
    $name = setting('restaurant_name', 'Tasty Bites');

    $kb = [];
    // If a public HTTPS Mini App URL is configured, show the app button first.
    if ($MINIAPP !== '') {
        $kb[] = [['text' => '🍔 Open Menu App', 'web_app' => ['url' => $MINIAPP]]];
    }
    $kb[] = [['text' => '📋 Browse Menu (in chat)', 'callback_data' => 'menu']];
    $kb[] = [['text' => '🛒 My Cart', 'callback_data' => 'cart']];

    $tip = $MINIAPP === ''
        ? "\n\nBrowse our menu and order right here in Telegram."
        : "\n\nTap “Open Menu App” for the full experience, or browse right here in chat.";

    send($chatId, "🍽️  Welcome to {$name}!{$tip}", $kb);
}

function screen_menu(int $chatId): void
{
    global $pdo;
    $cats = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    if (!$cats) {
        send($chatId, 'The menu is not available yet. Please check back soon.');
        return;
    }
    $kb = [];
    foreach ($cats as $c) {
        $kb[] = [['text' => $c['name'], 'callback_data' => 'cat:' . $c['id']]];
    }
    $kb[] = [['text' => '🛒 My Cart', 'callback_data' => 'cart']];
    send($chatId, 'Choose a category:', $kb);
}

function screen_category(int $chatId, int $catId): void
{
    global $pdo;
    $cat = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
    $cat->execute([$catId]);
    $catName = $cat->fetchColumn() ?: 'Menu';

    $stmt = $pdo->prepare(
        'SELECT id, name, price FROM menu_items
         WHERE category_id = ? AND is_available = 1 ORDER BY name'
    );
    $stmt->execute([$catId]);
    $items = $stmt->fetchAll();

    if (!$items) {
        send($chatId, "No items available in {$catName} right now.",
            [[['text' => '⬅ Back to menu', 'callback_data' => 'menu']]]);
        return;
    }

    $kb = [];
    foreach ($items as $it) {
        $kb[] = [[
            'text'          => $it['name'] . ' — ' . money_fmt($it['price']) . '  ➕',
            'callback_data' => 'add:' . $it['id'],
        ]];
    }
    $kb[] = [
        ['text' => '⬅ Menu', 'callback_data' => 'menu'],
        ['text' => '🛒 Cart', 'callback_data' => 'cart'],
    ];
    send($chatId, "🍴 {$catName} — tap an item to add it:", $kb);
}

function screen_cart(int $chatId): void
{
    global $pdo, $CARTS;
    $cart = $CARTS[$chatId] ?? [];
    if (!$cart) {
        send($chatId, 'Your cart is empty.',
            [[['text' => '📋 View Menu', 'callback_data' => 'menu']]]);
        return;
    }

    $ids = array_keys($cart);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price FROM menu_items WHERE id IN ($ph)");
    $stmt->execute($ids);

    $lines = "🛒 Your cart:\n\n";
    $subtotal = 0.0;
    $kb = [];
    foreach ($stmt as $row) {
        $qty = $cart[$row['id']];
        $line = $row['price'] * $qty;
        $subtotal += $line;
        $lines .= "{$qty}× {$row['name']} — " . money_fmt($line) . "\n";
        $kb[] = [
            ['text' => '➖ ' . $row['name'],       'callback_data' => 'dec:' . $row['id']],
            ['text' => '❌',                        'callback_data' => 'del:' . $row['id']],
        ];
    }

    $t = compute_totals($subtotal);
    $lines .= "\nSubtotal: " . money_fmt($t['subtotal']);
    if ($t['tax'] > 0)     $lines .= "\nTax: " . money_fmt($t['tax']);
    if ($t['service'] > 0) $lines .= "\nService: " . money_fmt($t['service']);
    $lines .= "\nTotal: " . money_fmt($t['total']);

    $kb[] = [['text' => '✅ Checkout', 'callback_data' => 'checkout']];
    $kb[] = [
        ['text' => '📋 Menu',  'callback_data' => 'menu'],
        ['text' => '🗑 Clear', 'callback_data' => 'clear'],
    ];
    send($chatId, $lines, $kb);
}

function ask_order_type(int $chatId): void
{
    send($chatId, 'How would you like your order?', [[
        ['text' => '🍽 Dine in',   'callback_data' => 'type:dine_in'],
        ['text' => '🥡 Takeaway',  'callback_data' => 'type:takeaway'],
    ]]);
}

/** Create the order in the DB from the current cart. Returns [orderId, total]. */
function place_order(int $chatId, string $customerName, string $orderType, ?string $table): array
{
    global $pdo, $CARTS;
    $cart = $CARTS[$chatId] ?? [];
    if (!$cart) {
        return [0, 0.0];
    }

    $ids = array_keys($cart);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price FROM menu_items WHERE id IN ($ph)");
    $stmt->execute($ids);

    $rows = [];
    $subtotal = 0.0;
    foreach ($stmt as $r) {
        $qty = (int) $cart[$r['id']];
        $sub = $r['price'] * $qty;
        $subtotal += $sub;
        $rows[] = ['id' => $r['id'], 'name' => $r['name'], 'price' => $r['price'], 'qty' => $qty, 'sub' => $sub];
    }
    $t = compute_totals($subtotal);

    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO orders (customer_name, phone, order_type, table_number,
             subtotal, tax_amount, service_amount, total_amount, notes)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $customerName, null, $orderType,
        $orderType === 'dine_in' ? $table : null,
        $t['subtotal'], $t['tax'], $t['service'], $t['total'],
        'Placed via Telegram',
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $line = $pdo->prepare(
        'INSERT INTO order_items (order_id, menu_item_id, item_name, price, quantity, subtotal)
         VALUES (?,?,?,?,?,?)'
    );
    foreach ($rows as $r) {
        $line->execute([$orderId, $r['id'], $r['name'], $r['price'], $r['qty'], $r['sub']]);
    }
    $pdo->commit();

    $CARTS[$chatId] = [];
    return [$orderId, $t['total']];
}

// ---------- Update handling ----------
function handle_update(array $u): void
{
    global $CARTS, $STATE;

    // A) Text messages
    if (isset($u['message']['text'])) {
        $chatId = (int) $u['message']['chat']['id'];
        $text   = trim($u['message']['text']);
        $from   = $u['message']['from'] ?? [];

        // Awaiting a table number for a dine-in checkout?
        if (($STATE[$chatId]['await'] ?? '') === 'table') {
            $table = $text !== '' ? $text : '-';
            unset($STATE[$chatId]);
            $name = trim(($from['first_name'] ?? 'Guest') . ' ' . ($from['last_name'] ?? ''));
            [$oid, $total] = place_order($chatId, $name ?: 'Guest', 'dine_in', $table);
            if ($oid) {
                send($chatId, "✅ Order #{$oid} placed!\nTable {$table} · Total " . money_fmt($total) .
                    "\n\nThank you — we're preparing it now.",
                    [[['text' => '📋 Order again', 'callback_data' => 'menu']]]);
            } else {
                send($chatId, 'Your cart was empty.');
            }
            return;
        }

        if ($text === '/start' || strtolower($text) === 'hi' || strtolower($text) === 'hello') {
            screen_welcome($chatId);
        } elseif ($text === '/menu') {
            screen_menu($chatId);
        } elseif ($text === '/cart') {
            screen_cart($chatId);
        } else {
            send($chatId, "Type /start to begin, /menu to browse, or /cart to review your order.");
        }
        return;
    }

    // B) Button taps (callback queries)
    if (isset($u['callback_query'])) {
        $cq     = $u['callback_query'];
        $chatId = (int) $cq['message']['chat']['id'];
        $data   = $cq['data'] ?? '';
        $cbId   = $cq['id'];
        $from   = $cq['from'] ?? [];

        if ($data === 'menu') {
            answer($cbId); screen_menu($chatId);
        } elseif ($data === 'cart') {
            answer($cbId); screen_cart($chatId);
        } elseif (str_starts_with($data, 'cat:')) {
            answer($cbId); screen_category($chatId, (int) substr($data, 4));
        } elseif (str_starts_with($data, 'add:')) {
            $id = (int) substr($data, 4);
            $CARTS[$chatId][$id] = ($CARTS[$chatId][$id] ?? 0) + 1;
            answer($cbId, 'Added to cart ✓');
        } elseif (str_starts_with($data, 'dec:')) {
            $id = (int) substr($data, 4);
            if (isset($CARTS[$chatId][$id])) {
                $CARTS[$chatId][$id]--;
                if ($CARTS[$chatId][$id] <= 0) unset($CARTS[$chatId][$id]);
            }
            answer($cbId, 'Updated'); screen_cart($chatId);
        } elseif (str_starts_with($data, 'del:')) {
            $id = (int) substr($data, 4);
            unset($CARTS[$chatId][$id]);
            answer($cbId, 'Removed'); screen_cart($chatId);
        } elseif ($data === 'clear') {
            $CARTS[$chatId] = [];
            answer($cbId, 'Cart cleared');
            send($chatId, 'Your cart is now empty.', [[['text' => '📋 View Menu', 'callback_data' => 'menu']]]);
        } elseif ($data === 'checkout') {
            answer($cbId);
            if (empty($CARTS[$chatId])) {
                send($chatId, 'Your cart is empty.', [[['text' => '📋 View Menu', 'callback_data' => 'menu']]]);
            } else {
                ask_order_type($chatId);
            }
        } elseif ($data === 'type:takeaway') {
            answer($cbId);
            $name = trim(($from['first_name'] ?? 'Guest') . ' ' . ($from['last_name'] ?? ''));
            [$oid, $total] = place_order($chatId, $name ?: 'Guest', 'takeaway', null);
            if ($oid) {
                send($chatId, "✅ Order #{$oid} placed!\nTakeaway · Total " . money_fmt($total) .
                    "\n\nThank you — we'll have it ready soon.",
                    [[['text' => '📋 Order again', 'callback_data' => 'menu']]]);
            } else {
                send($chatId, 'Your cart was empty.');
            }
        } elseif ($data === 'type:dine_in') {
            answer($cbId);
            $STATE[$chatId] = ['await' => 'table'];
            send($chatId, '🍽 Please reply with your table number:');
        } else {
            answer($cbId);
        }
        return;
    }
}

// ---------- Long-polling loop ----------
$me = tg('getMe');
if (!($me['ok'] ?? false)) {
    fwrite(STDERR, "Could not reach Telegram / invalid token. Response:\n" . json_encode($me) . "\n");
    exit(1);
}

// Configure the persistent chat menu button (the button next to the text box).
if ($MINIAPP !== '') {
    tg('setChatMenuButton', ['menu_button' => json_encode([
        'type'    => 'web_app',
        'text'    => 'Menu',
        'web_app' => ['url' => $MINIAPP],
    ])]);
    echo "Mini App enabled: {$MINIAPP}\n";
} else {
    tg('setChatMenuButton', ['menu_button' => json_encode(['type' => 'commands'])]);
    echo "Mini App URL not set — showing in-chat menu only.\n";
    echo "  (To enable the phone Mini App button, set TASTY_MINIAPP_URL or telegram/.miniapp_url to a PUBLIC https URL.)\n";
}

echo "Bot @{$me['result']['username']} is running. Press Ctrl+C to stop.\n";

$offset = 0;
while (true) {
    $resp = tg('getUpdates', ['offset' => $offset, 'timeout' => 50]);
    if (!is_array($resp) || empty($resp['ok'])) {
        sleep(2);
        continue;
    }
    foreach ($resp['result'] as $update) {
        $offset = $update['update_id'] + 1;
        try {
            handle_update($update);
        } catch (Throwable $e) {
            fwrite(STDERR, 'handler error: ' . $e->getMessage() . "\n");
        }
    }
}
