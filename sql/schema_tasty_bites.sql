-- ============================================================
--  Tasty Bites — Dedicated Database Schema (v2)
--  This app now lives in its OWN database, isolated from any
--  other project. Includes settings + order price breakdown.
-- ============================================================

CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT            NOT NULL,
    name         VARCHAR(150)   NOT NULL,
    description  TEXT           NULL,
    price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    image_path   VARCHAR(255)   NULL,
    is_available TINYINT(1)     NOT NULL DEFAULT 1,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    customer_name  VARCHAR(150)  NOT NULL,
    phone          VARCHAR(30)   NULL,
    order_type     ENUM('dine_in','takeaway') NOT NULL DEFAULT 'dine_in',
    table_number   VARCHAR(20)   NULL,
    subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status         ENUM('pending','preparing','ready','completed','cancelled')
                                 NOT NULL DEFAULT 'pending',
    total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes          TEXT          NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT            NOT NULL,
    menu_item_id INT            NULL,
    item_name    VARCHAR(150)   NOT NULL,
    price        DECIMAL(10,2)  NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,
    subtotal     DECIMAL(10,2)  NOT NULL,
    CONSTRAINT fk_oi_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oi_item
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    full_name     VARCHAR(150)  NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App configuration (tax rate, service charge, currency, etc.)
CREATE TABLE settings (
    setting_key   VARCHAR(50)  PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
