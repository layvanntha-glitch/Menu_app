<?php
/**
 * includes/settings.php — load and use app configuration.
 *
 * Requires $pdo (config/db.php) to be loaded first.
 * Provides:
 *   setting($key, $default)        -> a single setting value
 *   compute_totals($subtotal)      -> ['subtotal','tax','service','total'] breakdown
 *
 * Settings are read once per request and cached in a static array.
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Return one setting value (all settings are loaded + cached on first call).
 */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        global $pdo;
        $cache = [];
        foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
        // Make the currency symbol available to the money() helper globally.
        $GLOBALS['currency_symbol'] = $cache['currency_symbol'] ?? '$';
    }
    return $cache[$key] ?? $default;
}

/** The configured restaurant name (falls back to "Tasty Bites"). */
function restaurant_name(): string
{
    return (string) setting('restaurant_name', 'Tasty Bites');
}

/** Public URL of the uploaded logo, or '' if none is set. */
function brand_logo_url(): string
{
    $p = (string) setting('logo_path', '');
    return $p !== '' ? '/menu/' . $p : '';
}

/**
 * HTML for the brand mark: the uploaded logo image if set, otherwise the
 * default 🍽️ emoji. Pass a CSS class for the <img>.
 */
function brand_mark_html(string $imgClass = 'brand-logo'): string
{
    $url = brand_logo_url();
    if ($url !== '') {
        return '<img class="' . htmlspecialchars($imgClass, ENT_QUOTES) . '" src="'
             . htmlspecialchars($url, ENT_QUOTES) . '" alt="">';
    }
    return '🍽️';
}

/**
 * Given an items subtotal, compute the tax, service charge and grand total
 * using the configured percentage rates.
 */
function compute_totals(float $subtotal): array
{
    $taxRate     = (float) setting('tax_rate', '0');
    $serviceRate = (float) setting('service_charge_rate', '0');

    $tax     = round($subtotal * $taxRate / 100, 2);
    $service = round($subtotal * $serviceRate / 100, 2);
    $total   = round($subtotal + $tax + $service, 2);

    return [
        'subtotal'     => round($subtotal, 2),
        'tax'          => $tax,
        'tax_rate'     => $taxRate,
        'service'      => $service,
        'service_rate' => $serviceRate,
        'total'        => $total,
    ];
}
