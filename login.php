<?php
/**
 * login.php — customer sign-in.
 * On success returns to ?return=… (e.g. the checkout page) or the account page.
 */
require_once __DIR__ . '/includes/auth_user.php';
require_once __DIR__ . '/includes/settings.php';

$return = $_GET['return'] ?? ($_POST['return'] ?? '');
// Only allow local return paths (avoid open-redirects).
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
    $return = '/menu/account.php';
}

if (is_user()) {
    redirect($return);
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (user_login($pdo, $email, $password)) {
        redirect($return);
    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Sign In';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-card card">
    <h1 class="page-title" style="text-align:center;">Welcome back</h1>
    <p class="page-subtitle" style="text-align:center;">Sign in to your Tasty Bites account.</p>

    <?php if ($error): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/menu/login.php">
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e($email) ?>" autofocus required>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn--block">Sign In</button>
    </form>

    <p class="auth-alt">New here? <a href="/menu/register.php<?= $return !== '/menu/account.php' ? '?return=' . urlencode($return) : '' ?>">Create an account</a></p>
    <p class="login-hint">Demo: <code>demo@tastybites.test</code> / <code>demo123</code></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
