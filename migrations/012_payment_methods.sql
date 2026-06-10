-- 012: Pflegbare Zahlarten (Vorauswahl in den Formularen). Eine pro Zeile.
ALTER TABLE company_settings ADD COLUMN payment_methods TEXT NOT NULL DEFAULT '';
