<?php
/**
 * migrate_to_tasty_bites.php
 *
 * Moves the restaurant app into its OWN dedicated database (`tasty_bites`),
 * isolated from the pre-existing `restaurant_menu` (Laravel) database.
 *
 * Steps:
 *   1. Safety check: refuse to run if `tasty_bites` already has tables.
 *   2. Create the `tasty_bites` database + schema (schema_tasty_bites.sql).
 *   3. Copy existing restaurant data across (categories, menu_items, orders,
 *      order_items, admins) — reading ONLY the restaurant app tables from the
 *      old database, never the corrupt `settings` table.
 *   4. Seed default settings.
 *
 * Run once:  php migrate_to_tasty_bites.php
 */
header('Content-Type: text/plain; charset=utf-8');

$OLD = 'restaurant_menu';
$NEW = 'tasty_bites';

echo "== Migrate restaurant app -> dedicated `$NEW` database ==\n\n";

try {
    // Connect with no default database selected.
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    // ---- 1) Safety checks -------------------------------------------------
    $dbExists = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($NEW))->fetch();
    if ($dbExists) {
        // If it exists, make sure it is empty so we never clobber real data.
        $count = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($NEW)
        )->fetchColumn();
        if ((int) $count > 0) {
            throw new RuntimeException("Database `$NEW` already exists and is NOT empty. Aborting to avoid data loss.");
        }
        echo "[1] `$NEW` exists but is empty — continuing.\n";
    } else {
        $pdo->exec("CREATE DATABASE `$NEW` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "[1] Created database `$NEW`.\n";
    }

    // ---- 2) Build the schema ---------------------------------------------
    $pdo->exec("USE `$NEW`");
    $schema = file_get_contents(__DIR__ . '/sql/schema_tasty_bites.sql');

    // Strip full-line SQL comments first, THEN split into statements, so a
    // leading comment block never causes a real CREATE statement to be skipped.
    $lines = array_filter(
        explode("\n", $schema),
        fn($l) => strpos(trim($l), '--') !== 0
    );
    $clean = implode("\n", $lines);

    foreach (explode(';', $clean) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
    echo "[2] Schema created in `$NEW`.\n";

    // ---- 3) Copy data from the old database ------------------------------
    // We insert in dependency order. Cross-database INSERT..SELECT works
    // because both databases live on the same server.
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $pdo->exec("INSERT INTO `$NEW`.categories  SELECT * FROM `$OLD`.categories");
    $catN = $pdo->query("SELECT COUNT(*) FROM `$NEW`.categories")->fetchColumn();

    $pdo->exec("INSERT INTO `$NEW`.menu_items  SELECT * FROM `$OLD`.menu_items");
    $itemN = $pdo->query("SELECT COUNT(*) FROM `$NEW`.menu_items")->fetchColumn();

    $pdo->exec("INSERT INTO `$NEW`.admins      SELECT * FROM `$OLD`.admins");
    $admN = $pdo->query("SELECT COUNT(*) FROM `$NEW`.admins")->fetchColumn();

    // orders: the OLD table has no subtotal/tax/service columns, so we list
    // the shared columns explicitly and fill the new ones (subtotal=total).
    $pdo->exec(
        "INSERT INTO `$NEW`.orders
            (id, customer_name, phone, order_type, table_number, status, total_amount, notes, created_at,
             subtotal, tax_amount, service_amount)
         SELECT id, customer_name, phone, order_type, table_number, status, total_amount, notes, created_at,
             total_amount, 0.00, 0.00
         FROM `$OLD`.orders"
    );
    $ordN = $pdo->query("SELECT COUNT(*) FROM `$NEW`.orders")->fetchColumn();

    $pdo->exec("INSERT INTO `$NEW`.order_items SELECT * FROM `$OLD`.order_items");
    $oiN = $pdo->query("SELECT COUNT(*) FROM `$NEW`.order_items")->fetchColumn();

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[3] Copied: $catN categories, $itemN items, $ordN orders, $oiN order-items, $admN admin(s).\n";

    // ---- 4) Seed default settings ----------------------------------------
    $defaults = [
        'restaurant_name'     => 'Tasty Bites',
        'currency_symbol'     => '$',
        'tax_rate'            => '8',
        'service_charge_rate' => '5',
    ];
    $ins = $pdo->prepare("INSERT INTO `$NEW`.settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
    echo "[4] Seeded default settings (tax 8%, service 5%).\n";

    echo "\n[OK] Migration complete. The app now uses `$NEW`.\n";
    echo "     Your old `$OLD` database was only READ from and is unchanged.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "\n[ERROR] " . $e->getMessage() . "\n";
}
