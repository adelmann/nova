-- 006: E-Mail-Versand. SMTP-Zugangsdaten und Absenderangaben. Ist kein
-- smtp_host gesetzt, fällt der Versand auf die PHP-Funktion mail() zurück.
ALTER TABLE company_settings ADD COLUMN smtp_host       TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN smtp_port       INTEGER NOT NULL DEFAULT 587;
ALTER TABLE company_settings ADD COLUMN smtp_user       TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN smtp_pass       TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN smtp_encryption TEXT NOT NULL DEFAULT 'tls'; -- none|tls|ssl
ALTER TABLE company_settings ADD COLUMN mail_from_email TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN mail_from_name  TEXT NOT NULL DEFAULT '';
