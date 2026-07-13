<?php
/**
 * admin/settings.php — edit app configuration
 * (restaurant name, currency symbol, tax rate, service charge rate).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin_role();

// The settings we allow editing, with simple validation rules.
$fields = [
    'restaurant_name'     => ['label' => 'Restaurant Name',       'type' => 'text'],
    'currency_symbol'     => ['label' => 'Currency Symbol',       'type' => 'text'],
    'tax_rate'            => ['label' => 'Tax Rate (%)',          'type' => 'rate'],
    'service_charge_rate' => ['label' => 'Service Charge (%)',    'type' => 'rate'],
    'kitchen_chat_id'     => ['label' => 'Kitchen Telegram Chat ID (optional)', 'type' => 'optional'],
];

/** Validate + save an uploaded logo; returns the new rel path or null (none). */
function save_logo_upload(): ?string
{
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES['logo'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo upload failed.');
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Logo is too large (max 2 MB).');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Logo must be a PNG, JPG, WEBP or GIF image.');
    }
    $name = 'logo_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/' . $name)) {
        throw new RuntimeException('Could not save the logo.');
    }
    return 'uploads/' . $name;
}

$error = '';
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [];
    foreach ($fields as $key => $meta) {
        $val = trim($_POST[$key] ?? '');
        if ($meta['type'] === 'rate') {
            if (!is_numeric($val) || $val < 0 || $val > 100) {
                $error = $meta['label'] . ' must be a number between 0 and 100.';
                break;
            }
            $val = (string) (float) $val;
        } elseif ($meta['type'] !== 'optional' && $val === '') {
            $error = $meta['label'] . ' cannot be empty.';
            break;
        }
        $values[$key] = $val;
    }

    // Handle the logo: upload a new one, or remove the current one.
    if ($error === '') {
        try {
            $oldLogo = (string) setting('logo_path', '');
            if (!empty($_POST['remove_logo'])) {
                if ($oldLogo && is_file(__DIR__ . '/../' . $oldLogo)) {
                    @unlink(__DIR__ . '/../' . $oldLogo);
                }
                $values['logo_path'] = '';
            } else {
                $newLogo = save_logo_upload();
                if ($newLogo !== null) {
                    if ($oldLogo && is_file(__DIR__ . '/../' . $oldLogo)) {
                        @unlink(__DIR__ . '/../' . $oldLogo);
                    }
                    $values['logo_path'] = $newLogo;
                }
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }

    if ($error === '') {
        // Upsert each setting (SQLite UPSERT syntax).
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
        );
        foreach ($values as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        redirect('/menu/admin/settings.php?saved=1');
    }
}

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/includes/header.php';
?>

<h1 class="admin-h1">Settings</h1>

<?php if ($error): ?>
    <div class="alert alert--error"><?= e($error) ?></div>
<?php elseif ($saved): ?>
    <div class="alert alert--success">Settings saved.</div>
<?php endif; ?>

<form method="post" action="/menu/admin/settings.php" enctype="multipart/form-data" class="card" style="max-width:520px;">
    <h2 class="card-title">Branding &amp; Settings</h2>

    <!-- Restaurant name -->
    <label class="field">
        <span>Restaurant Name</span>
        <input type="text" name="restaurant_name" value="<?= e(setting('restaurant_name', '')) ?>" required>
    </label>

    <!-- Logo -->
    <div class="field">
        <span>Logo</span>
        <div class="logo-row">
            <div class="logo-preview">
                <?php if (brand_logo_url() !== ''): ?>
                    <img src="<?= e(brand_logo_url()) ?>" alt="Current logo">
                <?php else: ?>
                    <span class="logo-ph">🍽️</span>
                <?php endif; ?>
            </div>
            <div>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
                <p class="muted" style="font-size:.8rem;margin-top:4px;">PNG, JPG, WEBP or GIF · max 2 MB. Shown in the header, footer and login.</p>
                <?php if (brand_logo_url() !== ''): ?>
                    <label style="display:flex;align-items:center;gap:6px;margin-top:6px;font-size:.85rem;">
                        <input type="checkbox" name="remove_logo" value="1"> Remove current logo (use 🍽️)
                    </label>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php foreach ($fields as $key => $meta): if ($key === 'restaurant_name') continue; ?>
        <label class="field">
            <span><?= e($meta['label']) ?></span>
            <?php if ($meta['type'] === 'rate'): ?>
                <input type="number" step="0.01" min="0" max="100" name="<?= e($key) ?>"
                       value="<?= e(setting($key, '0')) ?>" required>
            <?php elseif ($meta['type'] === 'optional'): ?>
                <input type="text" name="<?= e($key) ?>" value="<?= e(setting($key, '')) ?>"
                       placeholder="e.g. your Telegram user id — leave blank to disable">
            <?php else: ?>
                <input type="text" name="<?= e($key) ?>" value="<?= e(setting($key, '')) ?>" required>
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <button type="submit" class="btn btn--block">Save Settings</button>
</form>

<p class="muted" style="margin-top:16px;">
    Tax and service charge are applied to the items subtotal at checkout.
    Set a rate to <strong>0</strong> to hide it from customers.
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
