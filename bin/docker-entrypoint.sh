#!/usr/bin/env bash
# Entrypoint für die lokale Docker-Umgebung:
# Migriert die Datenbank und legt – falls noch kein Benutzer existiert –
# einen Standard-Admin an. Danach wird das übergebene CMD ausgeführt.
set -e

echo "» Nova (Docker, lokale Entwicklung)"

# Schema aktuell halten (idempotent).
php bin/migrate.php

# Beim allerersten Start einen Admin anlegen, sofern noch keiner existiert.
USER_COUNT=$(php -r '
    require "src/bootstrap.php";
    \Nova\Core\DB::init((require "config.php")["db_path"]);
    echo (int) \Nova\Core\DB::getInstance()->fetchColumn("SELECT COUNT(*) FROM user");
' 2>/dev/null || echo 0)

if [ "$USER_COUNT" = "0" ]; then
    ADMIN_EMAIL="${NOVA_ADMIN_EMAIL:-admin@nova.local}"
    ADMIN_PASS="${NOVA_ADMIN_PASSWORD:-changeme123}"
    ADMIN_NAME="${NOVA_ADMIN_NAME:-Admin}"
    php bin/seed-admin.php "$ADMIN_EMAIL" "$ADMIN_PASS" "$ADMIN_NAME" || true
    echo "» Standard-Admin angelegt: $ADMIN_EMAIL / $ADMIN_PASS  (bitte Passwort ändern)"
fi

echo "» Nova läuft auf http://localhost:8000"
exec "$@"
