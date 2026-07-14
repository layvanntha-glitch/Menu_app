# 🚀 Deploying Tasty Bites (Railway / Render)

This app keeps its data in **SQLite** and stores uploaded images on disk, and it
runs a **Telegram bot poller**. So it needs a host that gives you a *persistent
disk* and an *always-on container* — not a serverless platform. **Railway** is the
easiest; **Render** works the same way (notes at the bottom).

Everything is packaged in the repo:

| File | Purpose |
|---|---|
| `Dockerfile` | PHP 8.2 + Apache, with `pdo_sqlite`, `gd`, `mbstring` |
| `docker/app.conf` | Serves the app under `/menu/`, allows `.htaccess` |
| `docker/entrypoint.sh` | Wires the volume (DB + uploads) and starts the bot |
| `.dockerignore` | Keeps secrets (`.token`) and local DB out of the image |

The web app **and** the Telegram bot run in **one container** on purpose: Railway
volumes aren't shared between services, and the bot must read/write the *same*
SQLite file as the website.

---

## Railway — step by step

### 1. Push the code to GitHub
```bash
git add Dockerfile docker/ .dockerignore DEPLOY.md
git commit -m "Add Docker deploy (Railway/Render)"
git push
```
> `telegram/.token` and `data/` are already git-ignored — your bot token and local
> database are **not** uploaded.

### 2. Create the project
1. Go to <https://railway.app> → **New Project** → **Deploy from GitHub repo**.
2. Pick your `Menu_app` repo. Railway detects the `Dockerfile` and starts building.

### 3. Add a persistent volume  ← don't skip this
1. Open the service → **Variables/Settings** → **Volumes** → **New Volume**.
2. **Mount path:** `/data`
3. Save. (This is where `tasty_bites.sqlite` and uploaded images live. Without it,
   every redeploy wipes your orders and images.)

### 4. Generate the public URL
Service → **Settings → Networking → Generate Domain**. You'll get something like
`https://menu-app-production.up.railway.app`.
Your app will be at **`https://…up.railway.app/menu/`** (the bare domain redirects there).

### 5. Set environment variables
Service → **Variables** → add:

| Variable | Value | Why |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | `123456:ABC…` (from @BotFather) | bot + notifications |
| `TASTY_PUBLIC_URL` | `https://<your-domain>/menu/` | invoice links in messages |
| `TASTY_MINIAPP_URL` | `https://<your-domain>/menu/index.php` | the 🍽 Open Menu button |
| `ENABLE_BOT` | `1` | run the poller (set `0` to turn it off) |

> You only know the domain after step 4, so set these, then **Redeploy** once.

### 6. Open it
- Storefront: `https://<your-domain>/menu/`
- Admin: `https://<your-domain>/menu/admin/`  (`admin` / `admin123`)
- The DB auto-creates and seeds the sample menu on the first visit.

### 7. Test the bot
Message your bot `/start`. Because Railway serves real **HTTPS**, the Telegram
**Mini App** works properly here (unlike a local Cloudflare quick-tunnel). Place a
test order and watch the status notifications arrive.

### 8. Lock it down
Change the default passwords immediately (Admin → Settings, and the user accounts):
`admin/admin123`, `chef/chef123`, `demo@tastybites.test/demo123`.

---

## Important notes

- **Do not scale past 1 replica.** SQLite is a single file on one volume; multiple
  instances would corrupt it. Keep the service at 1 instance.
- **Backups:** your data is one file — `/data/db/tasty_bites.sqlite`. Download it
  periodically from the Railway volume (or `railway run` a copy) to back up.
- **Bot alternative:** the poller runs 24/7 and uses container hours. If you'd
  rather not run it constantly, set `ENABLE_BOT=0`; you can switch to a Telegram
  *webhook* later (a small code change) to make the bot event-driven.

---

## Render (equivalent)

1. **New → Web Service → Build from a repo**, pick the repo. Environment: **Docker**.
2. Add a **Disk**: mount path `/data`, size 1 GB. *(Persistent disks are a paid
   feature on Render — the free tier has no disk and spins down.)*
3. Add the same environment variables as the Railway table above. Render provides
   `$PORT` automatically; the container already listens on it.
4. Deploy. Your app is at `https://<service>.onrender.com/menu/`.

---

## Local test of the container (optional)

With Docker installed:
```bash
docker build -t tasty-bites .
docker run --rm -p 8080:80 \
  -e TELEGRAM_BOT_TOKEN=your_token \
  -e TASTY_PUBLIC_URL=http://localhost:8080/menu/ \
  -v tasty_data:/data \
  tasty-bites
# open http://localhost:8080/menu/
```
