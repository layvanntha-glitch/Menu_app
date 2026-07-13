<?php
/**
 * tg_link.php — called (once) by the Mini App front-end to hand the server the
 * signed Telegram initData. We verify it and remember the customer's chat id in
 * the session, so checkout can notify them even if initData isn't present on the
 * checkout page itself (it can be lost across in-app navigation).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';       // starts the session
require_once __DIR__ . '/includes/telegram_notify.php';

header('Content-Type: application/json');

$init = $_POST['init'] ?? '';
$user = tg_validate_init_data($init);

if ($user) {
    $_SESSION['tg_chat_id'] = (string) $user['id'];
    $_SESSION['tg_name']    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false]);
}
