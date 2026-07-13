<?php
/**
 * food.php — a single dish's detail page.
 *
 * Shows a photo gallery, description, price and "add to cart", plus social
 * features: favourite (❤), a 1–5 star rating, and customer comments.
 * Favourite / rate / comment require a customer account (guests are sent to
 * the login page and returned here afterwards).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth_user.php';

$id = (int) ($_GET['id'] ?? 0);

// Load the dish (+ its category name).
$stmt = $pdo->prepare(
    'SELECT m.*, c.name AS category_name
     FROM menu_items m JOIN categories c ON c.id = m.category_id
     WHERE m.id = ?'
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    $pageTitle = 'Not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p class="empty-emoji">🍽️</p><h2>Dish not found</h2>'
       . '<p><a class="btn" href="/menu/index.php">Back to menu</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$user   = current_user();
$selfUrl = '/menu/food.php?id=' . $id;

// -------- Handle POST actions (favourite / rate / comment) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$user) {
        // Must be logged in — bounce to login and come back here.
        redirect('/menu/login.php?return=' . urlencode($selfUrl));
    }

    if ($action === 'favorite') {
        $chk = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND menu_item_id = ?');
        $chk->execute([$user['id'], $id]);
        if ($chk->fetch()) {
            $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND menu_item_id = ?')->execute([$user['id'], $id]);
        } else {
            $pdo->prepare('INSERT INTO favorites (user_id, menu_item_id) VALUES (?,?)')->execute([$user['id'], $id]);
        }
    } elseif ($action === 'rate') {
        $stars = (int) ($_POST['stars'] ?? 0);
        if ($stars >= 1 && $stars <= 5) {
            $pdo->prepare(
                'INSERT INTO ratings (user_id, menu_item_id, stars) VALUES (?,?,?)
                 ON CONFLICT(user_id, menu_item_id) DO UPDATE SET stars = excluded.stars'
            )->execute([$user['id'], $id, $stars]);
        }
    } elseif ($action === 'comment') {
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            $pdo->prepare('INSERT INTO comments (user_id, menu_item_id, body) VALUES (?,?,?)')
                ->execute([$user['id'], $id, mb_substr($body, 0, 1000)]);
        }
    }
    redirect($selfUrl);
}

// -------- Gather display data --------
// Gallery = main image (if any) + extra photos.
$gallery = [];
if (!empty($item['image_path'])) {
    $gallery[] = $item['image_path'];
}
$imgStmt = $pdo->prepare('SELECT image_path FROM item_images WHERE menu_item_id = ? ORDER BY sort_order, id');
$imgStmt->execute([$id]);
foreach ($imgStmt as $row) {
    $gallery[] = $row['image_path'];
}

// Ratings: average + count, and this user's own rating.
$ra = $pdo->prepare('SELECT COUNT(*) AS n, AVG(stars) AS avg FROM ratings WHERE menu_item_id = ?');
$ra->execute([$id]);
$ragg = $ra->fetch();
$ratingCount = (int) $ragg['n'];
$ratingAvg   = $ratingCount ? round((float) $ragg['avg'], 1) : 0;

$myRating = 0;
$isFav = false;
if ($user) {
    $mr = $pdo->prepare('SELECT stars FROM ratings WHERE user_id = ? AND menu_item_id = ?');
    $mr->execute([$user['id'], $id]);
    $myRating = (int) $mr->fetchColumn();

    $fv = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND menu_item_id = ?');
    $fv->execute([$user['id'], $id]);
    $isFav = (bool) $fv->fetchColumn();
}

// Comments (newest first) with author name.
$cm = $pdo->prepare(
    'SELECT c.body, c.created_at, u.name
     FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.menu_item_id = ? ORDER BY c.created_at DESC'
);
$cm->execute([$id]);
$comments = $cm->fetchAll();

/** Render N filled + (5-N) empty stars. */
function stars_html(float $value): string
{
    $full = (int) round($value);
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star ' . ($i <= $full ? 'star--on' : '') . '">★</span>';
    }
    return $out;
}

$pageTitle = $item['name'];
require __DIR__ . '/includes/header.php';
?>

<p class="crumbs"><a href="/menu/index.php"><?= t('nav_menu') ?></a> ›
    <a href="/menu/index.php?cat=<?= (int) $item['category_id'] ?>"><?= e($item['category_name']) ?></a> ›
    <span><?= e($item['name']) ?></span></p>

<div class="food-detail">
    <!-- Gallery -->
    <div class="food-gallery">
        <?php if ($gallery): ?>
            <img id="foodMain" class="food-gallery__main" src="/menu/<?= e($gallery[0]) ?>" alt="<?= e($item['name']) ?>">
            <?php if (count($gallery) > 1): ?>
                <div class="food-thumbs">
                    <?php foreach ($gallery as $i => $g): ?>
                        <img class="food-thumb <?= $i === 0 ? 'is-active' : '' ?>" src="/menu/<?= e($g) ?>"
                             alt="" onclick="foodShow(this,'<?= e($g) ?>')">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="food-gallery__main food-gallery__ph">🍴</div>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="food-info">
        <span class="food-cat"><?= e($item['category_name']) ?></span>
        <h1 class="food-title"><?= e($item['name']) ?></h1>

        <div class="food-rating-row">
            <span class="stars"><?= stars_html($ratingAvg) ?></span>
            <?php if ($ratingCount): ?>
                <strong><?= number_format($ratingAvg, 1) ?></strong>
                <span class="muted">(<?= $ratingCount ?> <?= t('ratings_n') ?>)</span>
            <?php else: ?>
                <span class="muted"><?= t('no_ratings') ?></span>
            <?php endif; ?>
        </div>

        <p class="food-desc"><?= nl2br(e($item['description'] ?: t('no_desc'))) ?></p>

        <div class="food-buy">
            <?= price_html($item, 'food-price') ?>
            <?php if ($item['is_available']): ?>
                <form method="post" action="/menu/cart.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <button class="btn"><?= t('add_to_cart_p') ?></button>
                </form>
            <?php else: ?>
                <span class="badge-out"><?= t('unavailable') ?></span>
            <?php endif; ?>

            <form method="post" action="<?= e($selfUrl) ?>">
                <input type="hidden" name="action" value="favorite">
                <button class="btn btn--ghost fav-btn <?= $isFav ? 'is-fav' : '' ?>" title="<?= t('nav_favourites') ?>">
                    <?= $isFav ? t('saved_fav') : t('save_fav') ?>
                </button>
            </form>
        </div>

        <!-- Your rating -->
        <div class="rate-box">
            <span class="rate-label"><?= $myRating ? t('your_rating') : t('rate_dish') ?></span>
            <form method="post" action="<?= e($selfUrl) ?>" class="rate-stars">
                <input type="hidden" name="action" value="rate">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <button type="submit" name="stars" value="<?= $s ?>"
                            class="star-btn <?= $s <= $myRating ? 'star--on' : '' ?>"
                            title="<?= $s ?> star<?= $s === 1 ? '' : 's' ?>">★</button>
                <?php endfor; ?>
            </form>
            <?php if (!$user): ?><span class="muted"> (<a href="/menu/login.php?return=<?= urlencode($selfUrl) ?>"><?= t('signin_to_rate') ?></a>)</span><?php endif; ?>
        </div>
    </div>
</div>

<!-- Comments -->
<section class="comments card">
    <h2 class="card-title">💬 <?= t('comments') ?> (<?= count($comments) ?>)</h2>

    <?php if ($user): ?>
        <form method="post" action="<?= e($selfUrl) ?>" class="comment-form">
            <input type="hidden" name="action" value="comment">
            <textarea name="body" rows="3" maxlength="1000" placeholder="<?= t('comment_ph') ?>" required></textarea>
            <button class="btn"><?= t('post_comment') ?></button>
        </form>
    <?php else: ?>
        <p class="muted"><a href="/menu/login.php?return=<?= urlencode($selfUrl) ?>"><?= t('nav_signin') ?></a> <?= t('signin_comment') ?></p>
    <?php endif; ?>

    <?php if (empty($comments)): ?>
        <p class="muted" style="margin-top:16px;"><?= t('no_comments') ?></p>
    <?php else: ?>
        <ul class="comment-list">
            <?php foreach ($comments as $c): ?>
                <li class="comment">
                    <div class="comment-avatar"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></div>
                    <div class="comment-body">
                        <div class="comment-head">
                            <strong><?= e($c['name']) ?></strong>
                            <span class="muted"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></span>
                        </div>
                        <p><?= nl2br(e($c['body'])) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script>
    function foodShow(el, src) {
        document.getElementById('foodMain').src = '/menu/' + src;
        document.querySelectorAll('.food-thumb').forEach(function (t) { t.classList.remove('is-active'); });
        el.classList.add('is-active');
    }
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
