# 🍽️ Tasty Bites — Restaurant Menu & Ordering System

A complete restaurant **menu, ordering, and management** web app built with **PHP 8 + SQLite**.
Runs on **XAMPP** with only **Apache** — there is no database server to install or start,
no credentials to configure, and no build step. Clone it, open the page, and it works.

- 🛒 Customer storefront: menu, cart, checkout, invoices
- 🧑‍🍳 Staff back-office: dashboard, orders, kitchen display
- 🤖 Telegram bot ordering + live notifications
- 🌐 Three languages: **English / ភាសាខ្មែរ / 中文**

---

## ✨ Features

### Customer storefront — `http://localhost/menu/`
- Browse the menu grouped by category, with search + category filters
- **Food detail page** with photo gallery, ⭐ ratings, ❤️ favourites, and 💬 comments
- **Per-item discounts** (percent or fixed amount), a **🔥 Special Offers** section, and a
  best-seller / discount **popup**
- Session-based **cart** (update quantities, remove, clear)
- **Checkout**: dine-in (table number) or takeaway, tax + service charge
- **Invoice**: on-screen, **printable**, and **PDF download** (self-contained, no libraries)
- Customer **accounts** (sign up / sign in), order history, saved favourites
- **Light/Dark** theme toggle · fully **responsive** for phones

### Admin / staff back-office — `http://localhost/menu/admin/`
- Secure login (bcrypt) with two **roles**: **Admin** and **Chef**
- **Dashboard**: daily stats, an animated **Orders-by-Food** bar chart, and a **Top Seller** banner
- **Menu items**: create / edit / enable-disable / delete, single image + **multi-photo gallery**, discounts
- **Categories**, **Orders** (filter by status, view detail, advance status), **Settings**
- **Kitchen Display (KDS)**: live board for chefs to advance orders (pending → preparing → ready)
- Change **restaurant name & logo**, currency symbol, tax & service rates

### Telegram bot (optional)
- Order from a **Telegram Mini App** on a phone
- Detailed **notifications** to the customer at every step (received → preparing → ready → completed),
  each with an itemised recap and an **invoice link/button**
- **Kitchen alerts** to the chef for new orders

### Languages 🌐
- Switch the **interface** between **English / Khmer / 中文** from the header (both storefront & admin).
  Choice is remembered per visitor (session + cookie). Menu item names stay as the admin typed them.

---

## 🧰 Tech stack

| Layer     | Choice                                                                 |
|-----------|-----------------------------------------------------------------------|
| Backend   | Plain **PHP 8** with **PDO** prepared statements (no framework)        |
| Database  | **SQLite** — one file (`data/tasty_bites.sqlite`), auto-created        |
| Frontend  | Server-rendered HTML + CSS (design tokens, light/dark), no build step  |
| PDF       | Self-contained pure-PHP generator (`includes/pdf.php`)                 |
| Bot       | Telegram Bot API (long-poll) + Mini Apps, via `cURL`                   |

---

## 🚀 Setup on a new machine (Windows + XAMPP)

### 1. Install XAMPP
Download and install **XAMPP** (PHP 8.1+): <https://www.apachefriends.org/>.
You only need **Apache** — you do **not** need to start MySQL.

Make sure PHP has these extensions enabled (they are on by default in XAMPP): `pdo_sqlite`, `sqlite3`, `curl`, `gd`, `mbstring`.

### 2. Get the code — **folder must be named `menu`**
The app uses absolute URLs like `/menu/…`, so it must live at `htdocs/menu`.

```bash
cd C:/xampp/htdocs
git clone https://github.com/layvanntha-glitch/Menu_app.git menu
```

> If you download the ZIP instead, extract it so the path is `C:\xampp\htdocs\menu\index.php`.

### 3. Start Apache
Open the **XAMPP Control Panel** → **Start** Apache.

### 4. Open the app
Visit **<http://localhost/menu/>**.

That's it. On the first request, `config/db.php` automatically:
- creates `data/tasty_bites.sqlite`,
- builds the schema,
- seeds a sample menu, the settings, and the default staff/demo accounts.

> Optional scripted (re)install:
> ```bash
> C:/xampp/php/php.exe setup_sqlite.php          # create DB with sample data
> C:/xampp/php/php.exe setup_sqlite.php --force  # rebuild from scratch
> ```

---

## 🔑 Default accounts

| Role      | URL                              | Username / Email          | Password   |
|-----------|----------------------------------|---------------------------|------------|
| Admin     | `/menu/admin/`                   | `admin`                   | `admin123` |
| Chef      | `/menu/admin/` (→ Kitchen)       | `chef`                    | `chef123`  |
| Customer  | `/menu/login.php`                | `demo@tastybites.test`    | `demo123`  |

> **Change these passwords before any real use.**

---

## 🤖 Telegram bot setup (optional)

1. In Telegram, message **@BotFather** → `/newbot` → copy the **token**.
2. Save the token to a file (this file is git-ignored and never committed):
   ```bash
   cp telegram/.token.example telegram/.token
   # then edit telegram/.token and paste your real token
   ```
3. Run the bot poller (keeps listening for `/start` and orders):
   ```bash
   telegram/start_bot.bat        # or:  C:/xampp/php/php.exe telegram/bot.php
   ```
4. **Phone Mini App / invoice links** need a public **HTTPS** URL. The easy way is a
   Cloudflare quick tunnel:
   ```bash
   cloudflared tunnel --url http://localhost
   ```
   Put the resulting `https://xxxx.trycloudflare.com/menu/index.php` into
   `telegram/.miniapp_url` (also git-ignored).
   > Quick-tunnel URLs are **temporary** — they change on restart. Update `.miniapp_url`
   > with the new URL each time (or use a fixed domain / named tunnel for production).
5. **Kitchen alerts:** in **Admin → Settings → Kitchen Telegram Chat ID**, put a **numeric**
   chat id (your own, or a group's). Do **not** use the bot's `@username` — a bot can't message itself.

See `telegram/README.md` for more detail.

---

## 📱 Testing on your phone

Your phone can't open `localhost` (that means "this PC"). Use one of these:

### Option A — same Wi‑Fi (quickest, no tunnel)
1. Make sure the phone is on the **same Wi‑Fi** as the PC running Apache.
2. Find the PC's IPv4: run `ipconfig` and look for the Wi‑Fi adapter (e.g. `192.168.0.113`).
3. On the phone open `http://<that-ip>/menu/` (e.g. `http://192.168.0.113/menu/`).
   > If it doesn't load, allow Apache through **Windows Firewall** once (Private networks).

### Option B — public HTTPS tunnel (needed for the Telegram Mini App)
The Telegram Mini App and the invoice links in bot messages require a public **HTTPS** URL.
Just run the included one-click helper:

```
telegram/start_tunnel.bat
```

It starts a Cloudflare tunnel and **automatically**:
- writes the new URL into `telegram/.miniapp_url`, and
- re-points the Telegram **🍽 Open Menu** button to it.

Leave the window open while you test. The public URL is printed in the window; open it on the
phone, or tap **🍽 Open Menu** inside the bot.

> ⚠️ Free quick-tunnel URLs rotate every time the tunnel restarts. If the phone stops loading
> later, just run `start_tunnel.bat` again — it re-links everything. For a permanent fixed URL,
> use a **named** Cloudflare tunnel (a real domain) instead.

---

## 📁 Project structure

```
menu/
├── config/db.php              # PDO/SQLite connection (auto-creates + migrates the DB)
├── includes/                  # shared helpers, header/footer, i18n, telegram, pdf
│   ├── functions.php          # e(), money(), cart, discounts, loads i18n
│   ├── i18n.php               # EN/KM/ZH translations + t()
│   ├── lang_switch.php        # the language selector partial
│   ├── settings.php           # settings(), totals, branding
│   ├── telegram_notify.php    # bot messages (orders, status, invoice links)
│   └── pdf.php                # self-contained PDF generator
├── admin/                     # protected back-office
│   ├── auth.php               # login / session guard / roles
│   ├── includes/              # admin layout
│   ├── index.php              # dashboard (stats + charts)
│   ├── orders.php  kitchen.php  items.php  categories.php  settings.php
├── assets/css/                # style.css (site) + admin.css (panel)
├── uploads/                   # uploaded menu images (script execution blocked)
├── data/                      # tasty_bites.sqlite (web access blocked) — git-ignored
├── sql/                       # schema_sqlite.sql + bootstrap_sqlite.php (schema/seed/migrate)
├── telegram/                  # bot.php, start_bot.bat, start_tunnel.bat + tunnel.php,
│                              #   .token & .miniapp_url (both git-ignored)
├── index.php  cart.php  checkout.php  order_confirmation.php  invoice.php
├── food.php  favorites.php  account.php  login.php  register.php  logout.php
└── setup_sqlite.php           # optional scripted installer
```

---

## 🔒 Security notes

- All DB access uses **prepared statements** (SQL-injection safe).
- All output is escaped with `htmlspecialchars` (XSS safe).
- Passwords are stored as **bcrypt** hashes and checked with `password_verify`.
- Telegram Mini App identity is verified via **HMAC** of the signed `initData`.
- Image uploads are validated by real MIME type, size-limited, and stored under random names;
  `uploads/` and `data/` block script execution / web access via `.htaccess`.
- **Never commit** `telegram/.token` (the bot token) or `data/tasty_bites.sqlite`
  (customer data) — both are already in `.gitignore`.

---

## 🩺 Troubleshooting

| Symptom | Fix |
|---|---|
| CSS/pages 404 or links broken | The folder must be named **`menu`** under `htdocs`. |
| "database is locked" | Close other processes using the SQLite file; retry. |
| Images don't upload | Enable the **gd** extension in `php.ini`; check `uploads/` is writable. |
| Bot doesn't reply | Confirm `telegram/.token` is correct and `bot.php` is running. |
| No Telegram notification | You must be **logged in** when ordering (links your account to your chat), or order from inside the Mini App. |
| Invoice link in Telegram won't open | The Cloudflare tunnel must be running and `telegram/.miniapp_url` up to date. |

---

Built with PHP 8 + SQLite. No framework, no build tools, no database server.
