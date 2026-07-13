<?php
/**
 * favorites.php — the dishes a signed-in customer has hearted.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth_user.php';

require_user('/menu/favorites.php');
$user = current_user();

$stmt = $pdo->prepare(
    'SELECT m.id, m.name, m.description, m.price, m.discount_type, m.discount_value, m.image_path, m.is_available
     FROM favorites f JOIN menu_items m ON m.id = f.menu_item_id
     WHERE f.user_id = ? ORDER BY f.created_at DESC'
);
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

$pageTitle = t('my_favourites');
require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">❤️ <?= t('my_favourites') ?></h1>
<p class="page-subtitle"><?= t('fav_subtitle') ?></p>

<?php if (empty($items)): ?>
    <div class="empty-state">
        <p class="empty-emoji">🤍</p>
        <h2><?= t('no_favs') ?></h2>
        <p><?= t('no_favs_sub') ?></p>
        <p style="margin-top:20px;"><a href="/menu/index.php" class="btn"><?= t('browse_menu') ?></a></p>
    </div>
<?php else: ?>
    <div class="menu-grid">
        <?php foreach ($items as $item): ?>
            <article class="menu-card">
                <a class="menu-card__media" href="/menu/food.php?id=<?= (int) $item['id'] ?>">
                    <?php if (!empty($item['image_path'])): ?>
                        <img class="menu-card__img" loading="lazy" src="/menu/<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>">
                    <?php else: ?>
                        <div class="menu-card__img--placeholder">🍴</div>
                    <?php endif; ?>
                </a>
                <div class="menu-card__body">
                    <h3 class="menu-card__name"><a href="/menu/food.php?id=<?= (int) $item['id'] ?>"><?= e($item['name']) ?></a></h3>
                    <p class="menu-card__desc"><?= e($item['description']) ?></p>
                    <div class="menu-card__footer">
                        <?= price_html($item) ?>
                        <?php if ($item['is_available']): ?>
                            <form method="post" action="/menu/cart.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn--sm">＋ <?= t('add') ?></button>
                            </form>
                        <?php else: ?>
                            <span class="badge-out"><?= t('unavailable') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
