#!/bin/sh
# Prepare the persistent volume, retarget Apache to $PORT, optionally run the
# Telegram bot poller alongside Apache (they share the one SQLite file on /data).
set -e

APP=/var/www/html/menu
: "${PORT:=80}"

# --- Apache: listen on the platform-provided port ---------------------------
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# --- Persistent storage (volume mounted at /data) ---------------------------
# DB and uploads live on the volume so redeploys/restarts never wipe them.
mkdir -p /data/db /data/uploads
# Block direct web access to the SQLite file.
printf 'Require all denied\n' > /data/db/.htaccess
# Uploads are served (customer images) but must never execute as PHP.
printf 'php_flag engine off\nRequire all granted\n' > /data/uploads/.htaccess

# Point the app's data/ and uploads/ at the volume (replace the shipped dirs).
rm -rf "$APP/data" "$APP/uploads"
ln -sfn /data/db "$APP/data"
ln -sfn /data/uploads "$APP/uploads"

chown -R www-data:www-data /data

# --- Telegram bot poller (same container, shares the DB) --------------------
# Runs only when a token is set. Set ENABLE_BOT=0 to disable.
if [ -n "${TELEGRAM_BOT_TOKEN:-}" ] && [ "${ENABLE_BOT:-1}" = "1" ]; then
    echo "Tasty Bites: starting Telegram bot poller..."
    ( while true; do
        su -s /bin/sh www-data -c "php $APP/telegram/bot.php" || true
        echo "bot exited; restarting in 3s"
        sleep 3
      done ) &
else
    echo "Tasty Bites: bot poller disabled (no TELEGRAM_BOT_TOKEN or ENABLE_BOT=0)."
fi

exec "$@"
