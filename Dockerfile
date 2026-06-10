# Nova – Image NUR für die lokale Entwicklung.
# Produktiv läuft Nova auf Shared Hosting (PHP/SQLite), nicht in diesem Container.
FROM php:8.3-cli

# Für die PHP-Extensions benötigte System-Bibliotheken.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite mbstring gd zip dom \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Sinnvolle PHP-Defaults für Uploads (Belege/Logo).
RUN { \
        echo 'upload_max_filesize = 12M'; \
        echo 'post_max_size = 16M'; \
        echo 'memory_limit = 256M'; \
    } > /usr/local/etc/php/conf.d/nova.ini

# Der Projektcode wird per Volume gemountet (siehe docker-compose.yml),
# daher kein COPY – Änderungen sind sofort sichtbar.

EXPOSE 8000

ENTRYPOINT ["bin/docker-entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
