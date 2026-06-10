-- 005: Rechtliche Links (Impressum, Datenschutz) für die Fußzeile der
-- Anmeldeseite. Ziel-URLs sind frei unter Einstellungen pflegbar.
ALTER TABLE company_settings ADD COLUMN imprint_url TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN privacy_url TEXT NOT NULL DEFAULT '';
