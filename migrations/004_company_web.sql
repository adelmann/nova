-- 004: Website und Social-Media-Angaben für die Unternehmensdaten.
-- Werden in Rechnungen/Angeboten im Fußbereich eingebunden.
ALTER TABLE company_settings ADD COLUMN website TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN social_media TEXT NOT NULL DEFAULT '';
