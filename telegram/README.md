# 🤖 Tasty Bites — Telegram Ordering Bot

Order from the Tasty Bites menu directly in Telegram. Orders are saved into the
**same SQLite database** as the website, so they show up in the admin panel
(**Orders**) just like web orders.

## Why this works on localhost
The bot uses **long polling** — it calls *out* to Telegram's servers, so it
runs fine from your PC with **no public URL, no HTTPS certificate, no ngrok**.
(A webhook, by contrast, would need Telegram to reach *your* machine over public
HTTPS — not suitable for local testing.)

## Test it in 4 steps

1. **Create a bot** — in Telegram, open **@BotFather** → send `/newbot` →
   follow the prompts → copy the **token** (looks like `123456789:AAE...`).

2. **Give the bot its token** (either option):
   - Create a file `telegram/.token` containing only the token, **or**
   - Set an environment variable before running:
     - PowerShell: `$env:TELEGRAM_BOT_TOKEN="PASTE_TOKEN"`
     - CMD:        `set TELEGRAM_BOT_TOKEN=PASTE_TOKEN`

3. **Run the bot** from a terminal:
   ```
   C:\xampp\php\php.exe C:\xampp\htdocs\menu\telegram\bot.php
   ```
   You should see: `Bot @yourbot is running. Press Ctrl+C to stop.`

4. **Chat with it** — open your bot in Telegram, send `/start`, browse the
   menu, add items, and check out (dine-in asks for a table number; takeaway
   is instant). Then open **http://localhost/menu/admin/orders.php** — your
   Telegram order is there. 🎉

## Commands
- `/start` — welcome + buttons
- `/menu` — browse categories & items
- `/cart` — review cart, checkout, or clear

## Notes
- Keep the terminal window open — closing it stops the bot.
- Cart state lives in memory while the bot runs; restarting clears carts
  (orders already placed are safe in the database).
- Requires Apache **not** needed for the bot itself, but keep using the web
  admin panel to manage the orders it creates.
- The `telegram/` folder is blocked from web access via `.htaccess` so your
  token stays private.
