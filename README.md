# 🍽️ Tasty Bites — Restaurant Menu & Ordering System

A full-stack restaurant menu and online-ordering web app built with **PHP + SQLite**,
designed to run on **XAMPP** (only Apache is needed — no database server to start).

## Features

**Customer side** (`http://localhost/menu/`)
- Browse the menu grouped by category
- Add items to a session-based shopping cart
- Update quantities / remove items
- Checkout with dine-in (table number) or takeaway
- Order confirmation receipt

**Admin side** (`http://localhost/menu/admin/`)
- Secure login (bcrypt-hashed passwords)
- Dashboard with daily stats
- Manage categories (create / edit / show-hide / delete)
- Manage menu items (create / edit / enable-disable / delete) with **image upload**
- Manage orders: filter by status, view details, update status through the workflow

## Tech

- **Backend:** Plain PHP 8 with PDO prepared statements (no framework)
- **Database:** SQLite — a single self-contained file (`data/tasty_bites.sqlite`),
  no server, no credentials, nothing to configure
- **Frontend:** Server-rendered HTML + CSS with light/dark theming (no build step)

## Project structure

```
menu/
├── config/db.php              # PDO/SQLite connection (auto-creates the DB)
├── includes/                  # shared customer helpers + header/footer
├── admin/                     # protected back-office
│   ├── auth.php               # login / session guard
│   ├── includes/              # admin layout
│   ├── login.php  logout.php
│   ├── index.php              # dashboard
│   ├── categories.php  items.php  orders.php  receipt.php  settings.php
├── assets/css/                # style.css (site) + admin.css (panel)
├── uploads/                   # uploaded menu images (scripts blocked here)
├── data/                      # tasty_bites.sqlite  (web access blocked)
├── sql/schema_sqlite.sql      # SQLite schema
├── sql/bootstrap_sqlite.php   # schema + default-seed helpers
├── setup_sqlite.php           # one-time installer/migrator ← DELETE after setup
├── index.php  cart.php  checkout.php  order_confirmation.php
└── README.md
```

## Database

The app uses **SQLite** — the entire database is one file, `data/tasty_bites.sqlite`.
There is no database server, no username/password, and nothing to configure.
The `data/` folder is blocked from web access via `.htaccess`.

## First-time setup

Nothing to do — just open `http://localhost/menu/`. On the first request
`config/db.php` automatically creates the SQLite file, builds the schema, and
seeds a sample menu + the default admin account.

To (re)build the database from a script instead, run:

```
php setup_sqlite.php          # creates data/tasty_bites.sqlite with sample data
php setup_sqlite.php --force  # rebuild from scratch (also migrates old MariaDB data if present)
```

## Default admin login

| Username | Password   |
|----------|------------|
| `admin`  | `admin123` |

> Change this password for any real use.

## Security notes

- All database access uses **prepared statements** (SQL-injection safe).
- All output is escaped with `htmlspecialchars` (XSS safe).
- Passwords stored as **bcrypt** hashes; verified with `password_verify`.
- Image uploads are validated by real MIME type, size-limited, and stored
  under random filenames; the `uploads/` folder cannot execute scripts.
