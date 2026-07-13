<?php
/**
 * Small helper functions used across the whole app.
 * Include with:  require_once __DIR__ . '/includes/functions.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();   // needed for the cart and admin login
}

// Interface translations (English / Khmer / 中文). Provides t() + current_lang().
require_once __DIR__ . '/i18n.php';

/**
 * Escape a string for safe output in HTML.
 * ALWAYS use this when printing anything that came from a user or the
 * database, to prevent XSS (malicious script injection).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format a number as a price, e.g. 12.9 -> "$12.90".
 * Uses the configured currency symbol when settings have been loaded
 * (see includes/settings.php), otherwise falls back to "$".
 */
function money($amount): string
{
    $symbol = $GLOBALS['currency_symbol'] ?? '$';
    return $symbol . number_format((float) $amount, 2);
}

/**
 * The effective (possibly discounted) unit price of a menu item.
 * $item must include: price, discount_type ('none'|'percent'|'amount'), discount_value.
 */
function effective_price(array $item): float
{
    $price = (float) ($item['price'] ?? 0);
    $type  = $item['discount_type'] ?? 'none';
    $val   = (float) ($item['discount_value'] ?? 0);

    if ($type === 'percent' && $val > 0) {
        return max(0, round($price * (1 - $val / 100), 2));
    }
    if ($type === 'amount' && $val > 0) {
        return max(0, round($price - $val, 2));
    }
    return round($price, 2);
}

/** Does this item currently have an active discount? */
function has_discount(array $item): bool
{
    return effective_price($item) < (float) ($item['price'] ?? 0) - 0.001;
}

/** A short discount badge like "-20%" or "-$2.00" (empty if no discount). */
function discount_badge(array $item): string
{
    if (!has_discount($item)) {
        return '';
    }
    if (($item['discount_type'] ?? '') === 'percent') {
        return '-' . rtrim(rtrim(number_format((float) $item['discount_value'], 1), '0'), '.') . '%';
    }
    return '-' . money($item['discount_value']);
}

/**
 * Render an item's price: if discounted, show the old price struck through, the
 * new price, and a badge; otherwise just the price. $priceClass styles the
 * (current) price element.
 */
function price_html(array $item, string $priceClass = 'menu-card__price'): string
{
    $orig = (float) ($item['price'] ?? 0);
    $eff  = effective_price($item);
    $cls  = htmlspecialchars($priceClass, ENT_QUOTES);

    if ($eff < $orig - 0.001) {
        return '<span class="price-wrap">'
             . '<span class="price-old">' . money($orig) . '</span>'
             . '<span class="' . $cls . '">' . money($eff) . '</span>'
             . '<span class="discount-badge">' . e(discount_badge($item)) . '</span>'
             . '</span>';
    }
    return '<span class="' . $cls . '">' . money($eff) . '</span>';
}

/**
 * Return the total number of items currently in the cart (sum of quantities).
 * The cart lives in the session as:  $_SESSION['cart'][item_id] => quantity
 */
function cart_count(): int
{
    if (empty($_SESSION['cart'])) {
        return 0;
    }
    return array_sum($_SESSION['cart']);
}

/**
 * Redirect helper.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
