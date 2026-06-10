-- 011: Zwei-Faktor-Authentifizierung (TOTP). Secret im Klartext (zum Erzeugen
-- der Codes nötig), Recovery-Codes nur gehasht als JSON-Array.
ALTER TABLE user ADD COLUMN totp_secret    TEXT NOT NULL DEFAULT '';
ALTER TABLE user ADD COLUMN totp_enabled   INTEGER NOT NULL DEFAULT 0;
ALTER TABLE user ADD COLUMN recovery_codes TEXT NOT NULL DEFAULT '';
