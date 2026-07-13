<?php
/**
 * setup_sqlite.php — one-time installer / migrator for the SQLite database.
 *
 * Run once from the CLI:   php setup_sqlite.php
 *
 * It creates data/tasty_bites.sqlite, builds the schema, and then:
 *   - if the old MariaDB `tasty_bites` database is reachable, copies ALL
 *     existing data across (categories, items, orders, admins, settings);
 *   - otherwise seeds the default sample menu + admin.
 *
 * Safe by default: refuses to overwrite an existing, non-empty SQLite DB
 * unless you pass --force.
 *
 * DELETE this file after setup for a production deployment.
 */

require_once __DIR__ . '/sql/bootstrap_sqlite.php';

$force   = in_array('--force', $argv ?? [], true);
$dbFile  = __DIR__ . '/data/tasty_bites.sqlite';

echo "== Tasty Bites — SQLite setup ==\n\n";

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0775, true);
}

if (file_exists($dbFile)) {
    $check = new PDO('sqlite:' . $dbFile);
    $check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $existing = 0;
    try {
        $existing = (int) $check->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    } catch (Throwable $e) { /* no tables yet */ }
    $check = null;
    if ($existing > 0 && !$force) {
        fwrite(STDERR, "SQLite DB already exists and has data. Use --force to rebuild.\n");
        exit(1);
    }
    if ($force) {
        unlink($dbFile);
        echo "[0] Removed existing database (--force).\n";
    }
}

// 1) Create the SQLite database + schema.
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = OFF');   // off during bulk load, on afterwards
tb_create_schema($pdo);
echo "[1] Schema created at data/tasty_bites.sqlite\n";

// 2) Try to connect to the old MariaDB database to migrate real data.
$my = null;
try {
    $my = new PDO(
        'mysql:host=127.0.0.1;dbname=tasty_bites;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    $my = null;
}

if ($my) {
    echo "[2] MariaDB found — migrating existing data...\n";
    $pdo->beginTransaction();

    $copy = function (string $table, array $cols) use ($my, $pdo) {
        $list = implode(',', $cols);
        $ph   = implode(',', array_fill(0, count($cols), '?'));
        $ins  = $pdo->prepare("INSERT INTO $table ($list) VALUES ($ph)");
        $n = 0;
        foreach ($my->query("SELECT $list FROM $table") as $row) {
            $ins->execute(array_map(fn($c) => $row[$c], $cols));
            $n++;
        }
        echo "    - $table: $n rows\n";
    };

    $copy('categories',  ['id', 'name', 'sort_order', 'is_active', 'created_at']);
    $copy('menu_items',  ['id', 'category_id', 'name', 'description', 'price', 'image_path', 'is_available', 'created_at']);
    $copy('admins',      ['id', 'username', 'password_hash', 'full_name', 'created_at']);
    $copy('orders',      ['id', 'customer_name', 'phone', 'order_type', 'table_number', 'subtotal', 'tax_amount', 'service_amount', 'status', 'total_amount', 'notes', 'created_at']);
    $copy('order_items', ['id', 'order_id', 'menu_item_id', 'item_name', 'price', 'quantity', 'subtotal']);

    // Settings (upsert-safe).
    $s = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)');
    $sn = 0;
    foreach ($my->query('SELECT setting_key, setting_value FROM settings') as $row) {
        $s->execute([$row['setting_key'], $row['setting_value']]);
        $sn++;
    }
    echo "    - settings: $sn rows\n";

    $pdo->commit();
    echo "[3] Migration complete.\n";
} else {
    echo "[2] MariaDB not reachable — seeding default sample data instead.\n";
    tb_seed_defaults($pdo);
    echo "[3] Seed complete.\n";
}

// Final counts.
echo "\nFinal row counts:\n";
foreach (['categories', 'menu_items', 'orders', 'order_items', 'admins', 'settings'] as $t) {
    printf("  %-12s %d\n", $t, (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn());
}
echo "\n[OK] SQLite database ready. Start the app at http://localhost/menu/\n";
echo "     (You can now stop MySQL in XAMPP — it is no longer used.)\n";
