-- 010: Passwort-Zurücksetzen per E-Mail. Token wird nur gehasht gespeichert,
-- ist zeitlich begrenzt gültig und einmalig verwendbar.
ALTER TABLE user ADD COLUMN reset_token_hash TEXT NOT NULL DEFAULT '';
ALTER TABLE user ADD COLUMN reset_expires_at TEXT;
