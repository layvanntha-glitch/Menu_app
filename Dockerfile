# Tasty Bites — PHP 8 + Apache, single container (web + Telegram bot poller).
# Keeps SQLite + uploads on a persistent volume mounted at /data.
FROM php:8.2-apache

# --- PHP extensions the app uses: pdo_sqlite, gd (image uploads), mbstring ---
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libsqlite3-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_sqlite gd mbstring; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Apache: allow .htaccess (uploads/data protection) + redirect / -> /menu/
RUN a2enmod rewrite
COPY docker/app.conf /etc/apache2/conf-available/tasty.conf
RUN a2enconf tasty

# The app is served under /menu/ because it uses absolute /menu/... URLs.
# DocumentRoot stays the default /var/www/html; the app lives one level in.
COPY . /var/www/html/menu/

# Entrypoint wires the persistent volume and (optionally) starts the bot.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# Railway/Render inject $PORT; Apache is retargeted to it at startup.
ENV PORT=80
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
