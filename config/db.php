<?php
/**
 * Database connection (PDO + SQLite).
 * Include this file anywhere you need the database:
 *   require_once __DIR__ . '/config/db.php';   // gives you $pdo
 *
 * The whole database is a single file on disk — no server to start, no
 * username/password, nothing to configure. On the very first run the schema
 * and a sample menu are created automatically, so the app just works.
 */

// --- Where the database file lives -------------------------------------
// Kept OUTSIDE the document root logic in a dedicated data/ folder that is
// blocked from web access (see data/.htaccess).
define('DB_FILE', __DIR__ . '/../data/tasty_bites.sqlite');

$options = [
    // Throw exceptions on errors so we notice problems immediately.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return rows as associative arrays (['name' => '...']) by default.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    // Make sure the data/ directory exists.
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $isNew = !file_exists(DB_FILE);

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, $options);

    // SQLite does NOT enforce foreign keys unless we turn them on per
    // connection — required for ON DELETE CASCADE / SET NULL to work.
    $pdo->exec('PRAGMA foreign_keys = ON');

    // First run (or the file was deleted): build schema + seed sample data.
    require_once __DIR__ . '/../sql/bootstrap_sqlite.php';
    if ($isNew) {
        tb_create_schema($pdo);
        tb_seed_defaults($pdo);
    }
    // Cheap, idempotent — keeps older databases up to date (adds users table
    // and orders.user_id if they don't exist yet).
    tb_migrate($pdo);
} catch (PDOException $e) {
    // In a real production app you would log this, not print it.
    die('Database connection failed: ' . $e->getMessage());
}
