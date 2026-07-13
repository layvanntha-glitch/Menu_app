<?php
/**
 * admin/auth.php — authentication helpers for the admin area.
 *
 * Include this at the TOP of every protected admin page:
 *     require_once __DIR__ . '/auth.php';
 *     require_admin();          // stops here & redirects if not logged in
 *
 * A logged-in admin is remembered in the session as $_SESSION['admin'].
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';   // starts the session, gives e()/redirect()

/**
 * Attempt to log in with a username + password.
 * Returns true on success (and stores the admin in the session), false otherwise.
 */
function admin_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // password_verify compares the plain password against the stored bcrypt hash.
    if ($admin && password_verify($password, $admin['password_hash'])) {
        // Regenerate the session id on login to prevent session fixation.
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id'        => $admin['id'],
            'username'  => $admin['username'],
            'full_name' => $admin['full_name'],
            'role'      => $admin['role'] ?? 'admin',
        ];
        return true;
    }
    return false;
}

/** Is someone logged in as staff (admin or chef) right now? */
function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

/** The current staff member's role ('admin' or 'chef'), or '' if not logged in. */
function admin_role(): string
{
    return $_SESSION['admin']['role'] ?? '';
}

/** Is the current staff member a chef? */
function is_chef(): bool
{
    return admin_role() === 'chef';
}

/** Guard a page: if not logged in, redirect to the login screen. */
function require_admin(): void
{
    if (!is_admin()) {
        redirect('/menu/admin/login.php');
    }
}

/**
 * Guard an ADMIN-ONLY page. Chefs are limited to the Kitchen Display, so they
 * are bounced there instead of seeing the full back office.
 */
function require_admin_role(): void
{
    if (!is_admin()) {
        redirect('/menu/admin/login.php');
    }
    if (is_chef()) {
        redirect('/menu/admin/kitchen.php');
    }
}

/** Log the current admin out. */
function admin_logout(): void
{
    unset($_SESSION['admin']);
}
