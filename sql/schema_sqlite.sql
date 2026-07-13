-- ============================================================
--  Tasty Bites — SQLite schema
--  SQLite is a zero-config, file-based database. The whole
--  database lives in a single file (data/tasty_bites.sqlite),
--  so there is no server to start and nothing to configure.
--  Enable foreign keys per-connection with PRAGMA foreign_keys = ON.
-- ============================================================

CREATE TABLE IF NOT EXISTS categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS menu_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id  INTEGER NOT NULL,
    name         TEXT    NOT NULL,
    description  TEXT,
    price          NUMERIC NOT NULL DEFAULT 0,
    discount_type  TEXT    NOT NULL DEFAULT 'none',   -- 'none' | 'percent' | 'amount'
    discount_value NUMERIC NOT NULL DEFAULT 0,
    image_path     TEXT,
    is_available   INTEGER NOT NULL DEFAULT 1,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE,
    phone         TEXT,
    password_hash TEXT    NOT NULL,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS orders (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER,
    tg_chat_id     TEXT,
    customer_name  TEXT    NOT NULL,
    phone          TEXT,
    order_type     TEXT    NOT NULL DEFAULT 'dine_in'
                           CHECK (order_type IN ('dine_in','takeaway')),
    table_number   TEXT,
    subtotal       NUMERIC NOT NULL DEFAULT 0,
    tax_amount     NUMERIC NOT NULL DEFAULT 0,
    service_amount NUMERIC NOT NULL DEFAULT 0,
    status         TEXT    NOT NULL DEFAULT 'pending'
                           CHECK (status IN ('pending','preparing','ready','completed','cancelled')),
    total_amount   NUMERIC NOT NULL DEFAULT 0,
    notes          TEXT,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS order_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id     INTEGER NOT NULL,
    menu_item_id INTEGER,
    item_name    TEXT    NOT NULL,
    price        NUMERIC NOT NULL,
    quantity     INTEGER NOT NULL DEFAULT 1,
    subtotal     NUMERIC NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS admins (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    full_name     TEXT,
    role          TEXT    NOT NULL DEFAULT 'admin',   -- 'admin' or 'chef'
    created_at    TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key   TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_items_category ON menu_items(category_id);
CREATE INDEX IF NOT EXISTS idx_orderitems_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
