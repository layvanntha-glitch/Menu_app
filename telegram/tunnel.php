<?php
/**
 * telegram/tunnel.php — one-command public tunnel manager.
 *
 * Starts a Cloudflare quick tunnel to the local Apache, and as soon as the
 * public https URL appears it automatically:
 *   1. writes it to telegram/.miniapp_url   (used by the bot + invoice links)
 *   2. re-points the Telegram "Open Menu" button to the new URL
 *
 * So whenever the free tunnel URL changes, you just run this once and
 * everything (phone Mini App, invoice buttons) keeps working — no hand-editing.
 *
 * Usage:   double-click telegram/start_tunnel.bat
 *      or: C:\xampp\php\php.exe telegram\tunnel.php
 * Leave the window open while you use the phone. Press Ctrl+C to stop.
 */

require_once __DIR__ . '/../includes/telegram_notify.php';   // tg_bot_token()

$APP_PATH = '/menu/index.php';   // the storefront entry the Mini App opens
$LOCAL    = 'http://localhost:80';

// Locate the cloudflared binary.
$candidates = [
    'C:\\Program Files (x86)\\cloudflared\\cloudflared.exe',
    'C:\\Program Files\\cloudflared\\cloudflared.exe',
    'cloudflared',   // rely on PATH
];
$bin = null;
foreach ($candidates as $c) {
    if ($c === 'cloudflared' || is_file($c)) { $bin = $c; break; }
}
if ($bin === null) {
    fwrite(STDERR, "cloudflared not found. Install it, then run this again.\n");
    exit(1);
}

echo "Starting Cloudflare tunnel to {$LOCAL} ...\n";

$cmd = '"' . $bin . '" tunnel --url ' . $LOCAL . ' --no-autoupdate';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],   // cloudflared logs to stderr
];
$proc = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "Failed to launch cloudflared.\n");
    exit(1);
}
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$applied = false;

/** Point the Telegram menu button at the given Mini App URL. */
function set_menu_button(string $url): bool
{
    $token = tg_bot_token();
    if (!$token) { return false; }
    $ca = tg_ca_bundle();
    $payload = json_encode([
        'menu_button' => [
            'type'    => 'web_app',
            'text'    => '🍽 Open Menu',
            'web_app' => ['url' => $url],
        ],
    ]);
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/setChatMenuButton');
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 10,
    ];
    if ($ca !== '') { $opts[CURLOPT_CAINFO] = $ca; }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    curl_close($ch);
    return $raw !== false && (bool) (json_decode($raw, true)['ok'] ?? false);
}

// Stream cloudflared output; detect the public URL and wire everything up.
while (true) {
    $status = proc_get_status($proc);
    foreach ([1, 2] as $i) {
        $line = fgets($pipes[$i]);
        while ($line !== false) {
            echo $line;
            if (!$applied && preg_match('#https://[a-z0-9-]+\.trycloudflare\.com#i', $line, $m)) {
                $base = rtrim($m[0], '/');
                $full = $base . $APP_PATH;
                file_put_contents(__DIR__ . '/.miniapp_url', $full);
                echo "\n==============================================================\n";
                echo "  PUBLIC URL:  {$base}/menu/\n";
                echo "  Saved to telegram/.miniapp_url\n";
                echo "  Telegram menu button: " . (set_menu_button($full) ? "updated ✓" : "NOT updated (check bot token)") . "\n";
                echo "  Open this on your phone, or tap 🍽 Open Menu in the bot.\n";
                echo "==============================================================\n\n";
                $applied = true;
            }
            $line = fgets($pipes[$i]);
        }
    }
    if (!$status['running']) {
        echo "\ncloudflared stopped.\n";
        break;
    }
    usleep(200000);   // 0.2s
}

foreach ($pipes as $p) { if (is_resource($p)) { fclose($p); } }
proc_close($proc);
