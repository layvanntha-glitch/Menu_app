<?php
/**
 * sql/bootstrap_sqlite.php — create the SQLite schema and (optionally) seed it.
 *
 * Used in two places:
 *   - config/db.php auto-bootstraps a brand-new database on first run so the
 *     app works out of the box with zero manual setup.
 *   - setup_sqlite.php uses the same helpers for a scripted (re)install.
 *
 * These functions are safe to call repeatedly (schema uses IF NOT EXISTS,
 * seeding checks for existing rows first).
 */

/** Create all tables from the schema file. */
function tb_create_schema(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/schema_sqlite.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read schema_sqlite.sql');
    }
    $pdo->exec($sql);
}

/**
 * Idempotent, cheap migration run on every connection so databases created
 * before customer accounts existed pick up the new schema automatically:
 *   - creates the `users` table if missing
 *   - adds `orders.user_id` if missing (links an order to the account)
 */
function tb_migrate(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            name          TEXT    NOT NULL,
            email         TEXT    NOT NULL UNIQUE,
            phone         TEXT,
            password_hash TEXT    NOT NULL,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
        )"
    );

    $cols = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('user_id', $cols, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN user_id INTEGER');
    }
    if (!in_array('tg_chat_id', $cols, true)) {
        // Telegram chat id, captured when an order is placed from the Mini App,
        // so the bot can push status notifications to that user.
        $pdo->exec('ALTER TABLE orders ADD COLUMN tg_chat_id TEXT');
    }

    // Remember a customer's Telegram chat id on their account, so ANY future
    // order they place (even from the normal website) can be notified — not
    // only orders placed from inside the Telegram Mini App.
    $ucols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('tg_chat_id', $ucols, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN tg_chat_id TEXT');
        // Backfill from each user's most recent order that carried a chat id.
        $pdo->exec(
            "UPDATE users SET tg_chat_id = (
                 SELECT o.tg_chat_id FROM orders o
                 WHERE o.user_id = users.id AND o.tg_chat_id IS NOT NULL AND o.tg_chat_id <> ''
                 ORDER BY o.id DESC LIMIT 1
             )
             WHERE tg_chat_id IS NULL"
        );
    }

    // Per-item discounts.
    $mcols = $pdo->query('PRAGMA table_info(menu_items)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('discount_type', $mcols, true)) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN discount_type TEXT NOT NULL DEFAULT 'none'");
    }
    if (!in_array('discount_value', $mcols, true)) {
        $pdo->exec('ALTER TABLE menu_items ADD COLUMN discount_value NUMERIC NOT NULL DEFAULT 0');
    }

    // Staff roles: add the `role` column and a default chef account if missing.
    $acols = $pdo->query('PRAGMA table_info(admins)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('role', $acols, true)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
    }
    $chef = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
    $chef->execute(['chef']);
    if (!$chef->fetchColumn()) {
        $pdo->prepare('INSERT INTO admins (username, password_hash, full_name, role) VALUES (?,?,?,?)')
            ->execute(['chef', password_hash('chef123', PASSWORD_DEFAULT), 'Head Chef', 'chef']);
    }

    // Social features: favorites, star ratings, comments, and a photo gallery
    // (extra photos per menu item, beyond the single main image).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS favorites (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            menu_item_id INTEGER NOT NULL,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
            UNIQUE(user_id, menu_item_id),
            FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ratings (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            menu_item_id INTEGER NOT NULL,
            stars        INTEGER NOT NULL CHECK(stars BETWEEN 1 AND 5),
            created_at   TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
            UNIQUE(user_id, menu_item_id),
            FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS comments (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            menu_item_id INTEGER NOT NULL,
            body         TEXT    NOT NULL,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS item_images (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            menu_item_id INTEGER NOT NULL,
            image_path   TEXT    NOT NULL,
            sort_order   INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
        )"
    );
}

/**
 * Seed default data (sample menu + admin + settings) — but only if the
 * database is empty, so we never clobber real data.
 */
function tb_seed_defaults(PDO $pdo): void
{
    $hasData = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0;
    if ($hasData) {
        return;
    }

    // Default staff accounts.
    $mk = $pdo->prepare('INSERT INTO admins (username, password_hash, full_name, role) VALUES (?,?,?,?)');
    $mk->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin']);
    $mk->execute(['chef',  password_hash('chef123',  PASSWORD_DEFAULT), 'Head Chef',     'chef']);

    // Demo customer account: demo@tastybites.test / demo123.
    $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)')
        ->execute(['Demo Customer', 'demo@tastybites.test', null, password_hash('demo123', PASSWORD_DEFAULT)]);

    // App settings.
    $set = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)');
    foreach ([
        'restaurant_name'     => 'Tasty Bites',
        'currency_symbol'     => '$',
        'tax_rate'            => '8',
        'service_charge_rate' => '5',
    ] as $k => $v) {
        $set->execute([$k, $v]);
    }

    // Categories (id => name).
    $cats = [
        1 => 'Appetizers',
        2 => 'Main Course',
        3 => 'Desserts',
        4 => 'Beverages',
    ];
    $catStmt = $pdo->prepare('INSERT INTO categories (id, name, sort_order, is_active) VALUES (?,?,?,1)');
    $sort = 1;
    foreach ($cats as $id => $name) {
        $catStmt->execute([$id, $name, $sort++]);
    }

    // Sample menu items: [category_id, name, description, price].
    $items = [
        [1, 'Spring Rolls',       'Crispy vegetable spring rolls with sweet chili sauce.', 4.50],
        [1, 'Garlic Bread',       'Toasted baguette with garlic butter and herbs.',        3.75],
        [2, 'Grilled Chicken',    'Char-grilled chicken breast with seasonal vegetables.', 12.90],
        [2, 'Beef Burger',        'Juicy beef patty, cheddar, lettuce, tomato, fries.',    10.50],
        [2, 'Margherita Pizza',   'Tomato, mozzarella and fresh basil.',                    9.90],
        [3, 'Chocolate Cake',     'Rich chocolate layer cake.',                             5.25],
        [3, 'Ice Cream',          'Two scoops, choice of vanilla or chocolate.',            3.50],
        [4, 'Fresh Orange Juice', 'Freshly squeezed orange juice.',                         3.20],
        [4, 'Iced Coffee',        'Chilled coffee served over ice.',                        2.90],
    ];
    $itemStmt = $pdo->prepare(
        'INSERT INTO menu_items (category_id, name, description, price, is_available) VALUES (?,?,?,?,1)'
    );
    foreach ($items as $it) {
        $itemStmt->execute($it);
    }
}
