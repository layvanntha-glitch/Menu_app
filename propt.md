# Tasty Bites — Project Scope & Status

A restaurant **menu + online-ordering** web app. Plain PHP 8 + **SQLite** on XAMPP
(Apache only — no DB server), no framework, PDO prepared statements throughout.
Runs at `http://localhost/menu/`. The database is a single file:
**`data/tasty_bites.sqlite`** (auto-created on first run; web access blocked).

## Scope — Customer side  ✅ complete
- [x] Browse menu grouped by active category
- [x] Search dishes + filter by category
- [x] Session cart: add / update quantity / remove / clear
- [x] Checkout: name, phone, dine-in (table no.) or takeaway, notes
- [x] Server-side validation + price breakdown (subtotal, tax, service, total)
- [x] Order saved in a DB transaction; confirmation / receipt page

## Scope — Admin side (`/menu/admin/`)  ✅ complete
- [x] Secure login (bcrypt, session regeneration) + logout guard
- [x] Dashboard: orders today, active orders, item count, revenue today, recent orders
- [x] Categories CRUD (create / edit / show-hide / delete-with-cascade)
- [x] Menu items CRUD with validated image upload (real MIME check, random name, 2 MB cap)
- [x] Orders: filter by status, view detail, advance status through the workflow
- [x] Print-friendly receipt per order
- [x] Settings: restaurant name, currency symbol, tax rate, service-charge rate

## Security  ✅
- Prepared statements everywhere (SQL-injection safe)
- All output escaped with `htmlspecialchars` (XSS safe)
- Bcrypt password hashing; `uploads/` cannot execute scripts (`.htaccess`)

## Status
**Finished and verified** — all 21 PHP files lint clean; every customer and admin
route exercised end-to-end (order placement, admin auth, receipt) with no runtime
errors. Default admin login: `admin` / `admin123` (change for real use).

## Env notes
- DB is now **SQLite** (`data/tasty_bites.sqlite`) — MySQL/MariaDB is no longer
  used or required; you can leave MySQL stopped in XAMPP.
- Do DB work via `C:\xampp\php\php.exe` (e.g. `php setup_sqlite.php --force`).
- Foreign keys (ON DELETE CASCADE / SET NULL) rely on `PRAGMA foreign_keys = ON`,
  which `config/db.php` sets on every connection.
