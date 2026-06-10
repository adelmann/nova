-- 014: E-Mail-Signatur und Textvorlagen für den Versand (Rechnung, Angebot).
-- Leer = eingebauter Standardtext. Platzhalter werden beim Versand ersetzt.
ALTER TABLE company_settings ADD COLUMN email_signature      TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN invoice_email_subject TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN invoice_email_body    TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN quote_email_subject   TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN quote_email_body      TEXT NOT NULL DEFAULT '';
