<?php
/**
 * admin/login.php — the admin sign-in screen.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Where to land after login depends on the staff role.
$landing = fn() => is_chef() ? '/menu/admin/kitchen.php' : '/menu/admin/index.php';

// If already logged in, go straight to the right place.
if (is_admin()) {
    redirect($landing());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (admin_login($pdo, $username, $password)) {
        redirect($landing());
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e(restaurant_name()) ?></title>
    <link rel="stylesheet" href="/menu/assets/css/style.css">
    <link rel="stylesheet" href="/menu/assets/css/admin.css">
</head>
<body class="login-body">
    <div class="login-card card">
        <h1 class="login-title"><?= brand_mark_html('login-logo') ?> <?= e(restaurant_name()) ?> Admin</h1>
        <p class="login-sub">Sign in to manage the menu and orders.</p>

        <?php if ($error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/menu/admin/login.php">
            <label class="field">
                <span>Username</span>
                <input type="text" name="username" autofocus required>
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn btn--block">Sign In</button>
        </form>

        <p class="login-hint">Admin: <code>admin</code> / <code>admin123</code> &nbsp;·&nbsp; Chef: <code>chef</code> / <code>chef123</code></p>
    </div>
</body>
</html>
