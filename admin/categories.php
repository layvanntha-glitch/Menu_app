<?php
/**
 * admin/categories.php — manage menu categories (CRUD).
 *
 * All actions post back to THIS file with an ?action= parameter, then
 * redirect (Post/Redirect/Get). A ?msg= flag carries a one-off notice.
 *
 * Actions: create, update, toggle (active/inactive), delete.
 */
require_once __DIR__ . '/auth.php';
require_admin_role();

// -------- Handle POST actions --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            redirect('/menu/admin/categories.php?msg=name_required');
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO categories (name, sort_order, is_active) VALUES (?,?,?)'
            );
            $stmt->execute([$name, $sort, $active]);
            redirect('/menu/admin/categories.php?msg=created');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE categories SET name=?, sort_order=?, is_active=? WHERE id=?'
            );
            $stmt->execute([$name, $sort, $active, $id]);
            redirect('/menu/admin/categories.php?msg=updated');
        }
    }

    if ($action === 'toggle') {
        $stmt = $pdo->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id=?');
        $stmt->execute([$id]);
        redirect('/menu/admin/categories.php?msg=updated');
    }

    if ($action === 'delete') {
        // Deleting a category also deletes its items (ON DELETE CASCADE).
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id=?');
        $stmt->execute([$id]);
        redirect('/menu/admin/categories.php?msg=deleted');
    }
}

// -------- Load data for display --------
// If ?edit=ID is present, we are editing that category; else adding a new one.
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id=?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

// All categories with a count of how many items each holds.
$categories = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id) AS item_count
     FROM categories c
     ORDER BY c.sort_order, c.name'
)->fetchAll();

// Friendly messages.
$messages = [
    'created'        => 'Category created.',
    'updated'        => 'Category updated.',
    'deleted'        => 'Category deleted.',
    'name_required'  => 'Category name is required.',
];
$msg = $_GET['msg'] ?? '';

$pageTitle = 'Categories';
$activeNav = 'categories';
require __DIR__ . '/includes/header.php';
?>

<h1 class="admin-h1">Categories</h1>

<?php if (isset($messages[$msg])): ?>
    <div class="alert <?= $msg === 'name_required' ? 'alert--error' : 'alert--success' ?>">
        <?= e($messages[$msg]) ?>
    </div>
<?php endif; ?>

<div class="checkout-layout">
    <!-- Add / edit form -->
    <form method="post" action="/menu/admin/categories.php" class="card">
        <h2 class="card-title"><?= $editing ? 'Edit Category' : 'Add Category' ?></h2>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <?php endif; ?>

        <label class="field">
            <span>Name <span class="req">*</span></span>
            <input type="text" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
        </label>
        <label class="field">
            <span>Sort Order</span>
            <input type="number" name="sort_order" value="<?= (int) ($editing['sort_order'] ?? 0) ?>">
        </label>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <input type="checkbox" name="is_active" value="1"
                   <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>>
            <span>Active (visible on menu)</span>
        </label>

        <button type="submit" class="btn btn--block"><?= $editing ? 'Save Changes' : 'Add Category' ?></button>
        <?php if ($editing): ?>
            <a href="/menu/admin/categories.php" class="btn btn--block btn--muted" style="margin-top:8px;">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- List -->
    <div class="card">
        <h2 class="card-title">All Categories</h2>
        <?php if (empty($categories)): ?>
            <p class="muted">No categories yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Order</th><th>Items</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= e($c['name']) ?></td>
                            <td><?= (int) $c['sort_order'] ?></td>
                            <td><?= (int) $c['item_count'] ?></td>
                            <td>
                                <?php if ($c['is_active']): ?>
                                    <span class="status-pill status-completed">Active</span>
                                <?php else: ?>
                                    <span class="status-pill status-cancelled">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="/menu/admin/categories.php?edit=<?= (int) $c['id'] ?>" class="btn btn--sm btn--ghost">Edit</a>
                                <form method="post" action="/menu/admin/categories.php" class="inline-form">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="btn btn--sm btn--muted"><?= $c['is_active'] ? 'Hide' : 'Show' ?></button>
                                </form>
                                <form method="post" action="/menu/admin/categories.php" class="inline-form"
                                      onsubmit="return confirm('Delete this category and ALL its items?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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

<?php require __DIR__ . '/includes/footer.php'; ?>
