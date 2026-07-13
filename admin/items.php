<?php
/**
 * admin/items.php — manage menu items (CRUD + image upload).
 *
 * Actions (POST): create, update, toggle (availability), delete.
 * Images are uploaded into /menu/uploads and the relative path is stored
 * in menu_items.image_path.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/settings.php';   // currency symbol
require_admin_role();

// Where uploaded images are stored (absolute path + public relative path).
const UPLOAD_DIR = __DIR__ . '/../uploads/';
const UPLOAD_REL = 'uploads/';
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;   // 2 MB
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

/**
 * Handle an uploaded image from $_FILES[$field].
 * Returns the relative path on success, '' if no file was uploaded,
 * or throws RuntimeException on a real error.
 */
function handle_image_upload(string $field, array $allowed): string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';   // no new file chosen — that's fine
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Image is too large (max 2 MB).');
    }
    // Verify the ACTUAL content type, not the browser-supplied name.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }
    // Build a safe, unique filename (never trust the original name).
    $ext = $allowed[$mime];
    $name = 'item_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return UPLOAD_REL . $name;
}

/** Delete an image file from disk if it exists. */
function delete_image(?string $relPath): void
{
    if ($relPath && is_file(__DIR__ . '/../' . $relPath)) {
        @unlink(__DIR__ . '/../' . $relPath);
    }
}

/** Validate + save a single uploaded file array. Returns rel path or null. */
function save_gallery_file(array $f, array $allowed): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($f['size'] > MAX_IMAGE_BYTES) {
        throw new RuntimeException('A gallery photo is too large (max 2 MB).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Gallery photos must be JPG, PNG, WEBP or GIF.');
    }
    $name = 'item_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
        throw new RuntimeException('Could not save a gallery photo.');
    }
    return UPLOAD_REL . $name;
}

/** Save every file from the multi-file "gallery[]" input for an item. */
function process_gallery(PDO $pdo, int $itemId, array $allowed): void
{
    if (empty($_FILES['gallery']) || !is_array($_FILES['gallery']['name'] ?? null)) {
        return;
    }
    $files = $_FILES['gallery'];
    $ins = $pdo->prepare('INSERT INTO item_images (menu_item_id, image_path, sort_order) VALUES (?,?,?)');
    for ($i = 0, $n = count($files['name']); $i < $n; $i++) {
        $rel = save_gallery_file([
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ], $allowed);
        if ($rel) {
            $ins->execute([$itemId, $rel, $i]);
        }
    }
}

$error = '';

// -------- Handle POST actions --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'create' || $action === 'update') {
            $name        = trim($_POST['name'] ?? '');
            $categoryId  = (int) ($_POST['category_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $price       = (float) ($_POST['price'] ?? 0);
            $available   = isset($_POST['is_available']) ? 1 : 0;

            // Discount: none / percent (%) / amount ($).
            $discountType  = in_array($_POST['discount_type'] ?? 'none', ['none', 'percent', 'amount'], true)
                ? $_POST['discount_type'] : 'none';
            $discountValue = (float) ($_POST['discount_value'] ?? 0);
            if ($discountType === 'none' || $discountValue < 0) {
                $discountValue = ($discountType === 'none') ? 0 : max(0, $discountValue);
            }

            if ($name === '')            throw new RuntimeException('Item name is required.');
            if ($categoryId <= 0)        throw new RuntimeException('Please choose a category.');
            if ($price < 0)              throw new RuntimeException('Price cannot be negative.');
            if ($discountType === 'percent' && $discountValue > 100) throw new RuntimeException('Percentage discount cannot exceed 100%.');
            if ($discountType === 'amount' && $discountValue > $price) throw new RuntimeException('A fixed discount cannot be more than the price.');

            $newImage = handle_image_upload('image', $allowedTypes);

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO menu_items (category_id, name, description, price, discount_type, discount_value, image_path, is_available)
                     VALUES (?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([$categoryId, $name, $description, $price, $discountType, $discountValue, $newImage ?: null, $available]);
                $newId = (int) $pdo->lastInsertId();
                process_gallery($pdo, $newId, $allowedTypes);
                redirect('/menu/admin/items.php?edit=' . $newId . '&msg=created');
            } else {
                // Fetch existing to know the current image.
                $cur = $pdo->prepare('SELECT image_path FROM menu_items WHERE id=?');
                $cur->execute([$id]);
                $existing = $cur->fetch();
                $imagePath = $existing['image_path'] ?? null;

                if ($newImage !== '') {
                    delete_image($imagePath);   // replace old image
                    $imagePath = $newImage;
                }

                $stmt = $pdo->prepare(
                    'UPDATE menu_items
                     SET category_id=?, name=?, description=?, price=?, discount_type=?, discount_value=?, image_path=?, is_available=?
                     WHERE id=?'
                );
                $stmt->execute([$categoryId, $name, $description, $price, $discountType, $discountValue, $imagePath, $available, $id]);
                process_gallery($pdo, $id, $allowedTypes);
                redirect('/menu/admin/items.php?edit=' . $id . '&msg=updated');
            }
        }

        if ($action === 'delimg') {
            $imgId = (int) ($_POST['img_id'] ?? 0);
            $cur = $pdo->prepare('SELECT image_path, menu_item_id FROM item_images WHERE id=?');
            $cur->execute([$imgId]);
            $row = $cur->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM item_images WHERE id=?')->execute([$imgId]);
                delete_image($row['image_path']);
                redirect('/menu/admin/items.php?edit=' . (int) $row['menu_item_id'] . '&msg=updated');
            }
            redirect('/menu/admin/items.php');
        }

        if ($action === 'toggle') {
            $stmt = $pdo->prepare('UPDATE menu_items SET is_available = 1 - is_available WHERE id=?');
            $stmt->execute([$id]);
            redirect('/menu/admin/items.php?msg=updated');
        }

        if ($action === 'delete') {
            $cur = $pdo->prepare('SELECT image_path FROM menu_items WHERE id=?');
            $cur->execute([$id]);
            $row = $cur->fetch();
            $pdo->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]);
            if ($row) {
                delete_image($row['image_path']);
            }
            redirect('/menu/admin/items.php?msg=deleted');
        }
    } catch (RuntimeException $e) {
        // Re-render the page below with an error banner.
        $error = $e->getMessage();
    }
}

// -------- Load data for display --------
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
$editGallery = [];
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id=?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
    if ($editing) {
        $g = $pdo->prepare('SELECT id, image_path FROM item_images WHERE menu_item_id=? ORDER BY sort_order, id');
        $g->execute([$editId]);
        $editGallery = $g->fetchAll();
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();

$items = $pdo->query(
    'SELECT m.*, c.name AS category_name
     FROM menu_items m
     JOIN categories c ON c.id = m.category_id
     ORDER BY c.sort_order, m.name'
)->fetchAll();

$messages = ['created' => 'Menu item created.', 'updated' => 'Menu item updated.', 'deleted' => 'Menu item deleted.'];
$msg = $_GET['msg'] ?? '';

$pageTitle = 'Menu Items';
$activeNav = 'items';
require __DIR__ . '/includes/header.php';
?>

<h1 class="admin-h1">Menu Items</h1>

<?php if ($error): ?>
    <div class="alert alert--error"><?= e($error) ?></div>
<?php elseif (isset($messages[$msg])): ?>
    <div class="alert alert--success"><?= e($messages[$msg]) ?></div>
<?php endif; ?>

<?php if (empty($categories)): ?>
    <div class="alert alert--error">
        You need to create a category first before adding items.
        <a href="/menu/admin/categories.php">Add a category →</a>
    </div>
<?php else: ?>
<div class="checkout-layout">
    <!-- Add / edit form -->
    <form method="post" action="/menu/admin/items.php" enctype="multipart/form-data" class="card">
        <h2 class="card-title"><?= $editing ? 'Edit Item' : 'Add Item' ?></h2>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <?php endif; ?>

        <label class="field">
            <span>Name <span class="req">*</span></span>
            <input type="text" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        </label>

        <label class="field">
            <span>Category <span class="req">*</span></span>
            <select name="category_id" required>
                <option value="">— choose —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"
                        <?= (isset($editing['category_id']) && $editing['category_id'] == $c['id']) ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Price <span class="req">*</span></span>
            <input type="number" name="price" step="0.01" min="0" value="<?= e($editing['price'] ?? '') ?>" required>
        </label>

        <?php $dt = $editing['discount_type'] ?? 'none'; ?>
        <div class="field">
            <span>Discount (optional)</span>
            <div style="display:flex;gap:8px;">
                <select name="discount_type" style="flex:0 0 42%;">
                    <option value="none"    <?= $dt === 'none' ? 'selected' : '' ?>>No discount</option>
                    <option value="percent" <?= $dt === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                    <option value="amount"  <?= $dt === 'amount' ? 'selected' : '' ?>>Fixed ($)</option>
                </select>
                <input type="number" name="discount_value" step="0.01" min="0" style="flex:1;"
                       value="<?= e(($editing['discount_value'] ?? 0) > 0 ? $editing['discount_value'] : '') ?>"
                       placeholder="e.g. 20 for 20% or $2">
            </div>
        </div>

        <label class="field">
            <span>Description</span>
            <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
        </label>

        <label class="field">
            <span>Image <?= $editing ? '(leave empty to keep current)' : '(optional)' ?></span>
            <input type="file" name="image" accept="image/*">
        </label>
        <?php if (!empty($editing['image_path'])): ?>
            <img src="/menu/<?= e($editing['image_path']) ?>" alt="" class="thumb" style="width:80px;height:80px;margin-bottom:12px;">
        <?php endif; ?>

        <label class="field">
            <span>Extra photos (gallery) — you can pick several</span>
            <input type="file" name="gallery[]" accept="image/*" multiple>
        </label>
        <?php if ($editing && $editGallery): ?>
            <div class="admin-gallery">
                <?php foreach ($editGallery as $g): ?>
                    <div class="admin-gallery__item">
                        <img src="/menu/<?= e($g['image_path']) ?>" alt="">
                        <form method="post" action="/menu/admin/items.php" class="inline-form"
                              onsubmit="return confirm('Remove this photo?');">
                            <input type="hidden" name="action" value="delimg">
                            <input type="hidden" name="img_id" value="<?= (int) $g['id'] ?>">
                            <button class="admin-gallery__del" title="Remove photo">✕</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($editing): ?>
            <p class="muted" style="font-size:.82rem;margin-bottom:12px;">No extra photos yet.</p>
        <?php endif; ?>

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <input type="checkbox" name="is_available" value="1"
                   <?= (!$editing || $editing['is_available']) ? 'checked' : '' ?>>
            <span>Available for ordering</span>
        </label>

        <button type="submit" class="btn btn--block"><?= $editing ? 'Save Changes' : 'Add Item' ?></button>
        <?php if ($editing): ?>
            <a href="/menu/admin/items.php" class="btn btn--block btn--muted" style="margin-top:8px;">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- List -->
    <div class="card">
        <h2 class="card-title">All Items (<?= count($items) ?>)</h2>
        <?php if (empty($items)): ?>
            <p class="muted">No items yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <?php if (!empty($it['image_path'])): ?>
                                    <img src="/menu/<?= e($it['image_path']) ?>" alt="" class="thumb">
                                <?php else: ?>
                                    <div class="thumb" style="display:flex;align-items:center;justify-content:center;background:#f0f0ee;">🍴</div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($it['name']) ?></td>
                            <td><?= e($it['category_name']) ?></td>
                            <td>
                                <?php if (has_discount($it)): ?>
                                    <span class="price-old"><?= money($it['price']) ?></span>
                                    <strong><?= money(effective_price($it)) ?></strong>
                                    <span class="discount-badge"><?= e(discount_badge($it)) ?></span>
                                <?php else: ?>
                                    <?= money($it['price']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($it['is_available']): ?>
                                    <span class="status-pill status-completed">Available</span>
                                <?php else: ?>
                                    <span class="status-pill status-cancelled">Off</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="/menu/admin/items.php?edit=<?= (int) $it['id'] ?>" class="btn btn--sm btn--ghost">Edit</a>
                                <form method="post" action="/menu/admin/items.php" class="inline-form">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                                    <button class="btn btn--sm btn--muted"><?= $it['is_available'] ? 'Disable' : 'Enable' ?></button>
                                </form>
                                <form method="post" action="/menu/admin/items.php" class="inline-form"
                                      onsubmit="return confirm('Delete this item?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                                    <button class="btn btn--sm btn--danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
