-- 008: Automatische Datensicherung. Token für den Web-Cron-Aufruf, ZIP-Passwort
-- sowie optionale Verteilung per E-Mail und/oder in ein Server-Verzeichnis.
ALTER TABLE company_settings ADD COLUMN backup_token    TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_password TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_email    TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_dir      TEXT NOT NULL DEFAULT '';
