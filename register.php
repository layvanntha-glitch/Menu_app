<?php
/**
 * register.php — customer sign-up. Logs the new user in on success.
 */
require_once __DIR__ . '/includes/auth_user.php';
require_once __DIR__ . '/includes/settings.php';

$return = $_GET['return'] ?? ($_POST['return'] ?? '');
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
    $return = '/menu/account.php';
}

if (is_user()) {
    redirect($return);
}

$error = '';
$form = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']  = trim($_POST['name'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $password      = $_POST['password'] ?? '';

    [$ok, $error] = user_register($pdo, $form['name'], $form['email'], $password, $form['phone']);
    if ($ok) {
        redirect($return);
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-card card">
    <h1 class="page-title" style="text-align:center;">Create your account</h1>
    <p class="page-subtitle" style="text-align:center;">Save your details and track your orders.</p>

    <?php if ($error): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/menu/register.php">
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <label class="field">
            <span>Name <span class="req">*</span></span>
            <input type="text" name="name" value="<?= e($form['name']) ?>" autofocus required>
        </label>
        <label class="field">
            <span>Email <span class="req">*</span></span>
            <input type="email" name="email" value="<?= e($form['email']) ?>" required>
        </label>
        <label class="field">
            <span>Phone (optional)</span>
            <input type="text" name="phone" value="<?= e($form['phone']) ?>">
        </label>
        <label class="field">
            <span>Password <span class="req">*</span></span>
            <input type="password" name="password" minlength="6" required>
        </label>
        <button type="submit" class="btn btn--block">Create Account</button>
    </form>

    <p class="auth-alt">Already have an account? <a href="/menu/login.php<?= $return !== '/menu/account.php' ? '?return=' . urlencode($return) : '' ?>">Sign in</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
