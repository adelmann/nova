-- 024: Konfigurierbare Backup-Häufigkeit. backup_interval_hours drosselt das
-- Anlegen (Mindestabstand), backup_email_interval_hours den E-Mail-Versand;
-- last_backup_email_at merkt sich den letzten Versand. 0 = jedes Mal anlegen
-- bzw. nie automatisch mailen.
ALTER TABLE company_settings ADD COLUMN backup_interval_hours INTEGER NOT NULL DEFAULT 24;
ALTER TABLE company_settings ADD COLUMN backup_email_interval_hours INTEGER NOT NULL DEFAULT 24;
ALTER TABLE company_settings ADD COLUMN last_backup_email_at TEXT;
