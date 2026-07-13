<?php
/**
 * includes/auth_user.php — customer (shopper) account helpers.
 *
 * Separate from the admin auth (admin/auth.php): customers log into the
 * storefront to see their order history and check out faster. The logged-in
 * customer is stored in the session as $_SESSION['user'].
 *
 * Requires config/db.php ($pdo) and includes/functions.php (session, redirect).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/** The currently logged-in customer, or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Is a customer logged in? */
function is_user(): bool
{
    return !empty($_SESSION['user']);
}

/** Guard a page: send guests to the login screen (optionally remembering where). */
function require_user(string $returnTo = ''): void
{
    if (!is_user()) {
        $q = $returnTo !== '' ? '?return=' . urlencode($returnTo) : '';
        redirect('/menu/login.php' . $q);
    }
}

/**
 * Attempt to log in. Returns true on success (and stores the user in session).
 */
function user_login(PDO $pdo, string $email, string $password): bool
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);   // prevent session fixation
        $_SESSION['user'] = [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
        ];
        return true;
    }
    return false;
}

/**
 * Register a new customer. Returns [true, ''] on success or [false, 'reason'].
 * On success the new user is also logged in.
 */
function user_register(PDO $pdo, string $name, string $email, string $password, string $phone = ''): array
{
    $name  = trim($name);
    $email = strtolower(trim($email));
    $phone = trim($phone);

    if ($name === '') {
        return [false, 'Please enter your name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Please enter a valid email address.'];
    }
    if (strlen($password) < 6) {
        return [false, 'Password must be at least 6 characters.'];
    }

    // Email already taken?
    $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        return [false, 'An account with that email already exists.'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)'
    );
    $stmt->execute([$name, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);

    $_SESSION['user'] = [
        'id'    => (int) $pdo->lastInsertId(),
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
    ];
    return [true, ''];
}

/** Log the current customer out. */
function user_logout(): void
{
    unset($_SESSION['user']);
}
