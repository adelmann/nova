-- 018: Automatische Zahlungserinnerung. 0 = aus; sonst Tage nach Fälligkeit,
-- ab denen der Cron eine Stufe-1-Erinnerung versendet (einmalig je Rechnung).
ALTER TABLE company_settings ADD COLUMN auto_reminder_days INTEGER NOT NULL DEFAULT 0;
