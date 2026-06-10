-- 015: Mehrbenutzer mit Rollen. Benutzer können deaktiviert werden (Login
-- gesperrt) statt gelöscht – schont das unveränderbare Audit-Log.
-- Erlaubte Rollen: admin | staff | accountant (Auswertung im Code via Acl).
ALTER TABLE user ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1;
