-- 022: Rabatt & Skonto auf Rechnungen sowie Mahngebühren/Verzugszinsen.
-- Rabatt = Nachlass auf die Positionssumme (vor USt). Skonto = Abzug bei
-- früher Zahlung (informativ + Ausgleichshilfe). Mahnwesen erhält konfigurierbare
-- Standard-Mahngebühr und Verzugszinssatz; Zinsen werden je Mahnung gespeichert.

-- Rechnung: Rabatt + Skonto-Konditionen
ALTER TABLE invoice ADD COLUMN discount_type TEXT NOT NULL DEFAULT 'none';   -- none | percent | amount
ALTER TABLE invoice ADD COLUMN discount_value INTEGER NOT NULL DEFAULT 0;     -- percent: Basispunkte (1000 = 10%), amount: Cent
ALTER TABLE invoice ADD COLUMN discount_cents INTEGER NOT NULL DEFAULT 0;     -- berechneter Rabattbetrag (Cent)
ALTER TABLE invoice ADD COLUMN skonto_percent_bp INTEGER NOT NULL DEFAULT 0;  -- Skontosatz in Basispunkten
ALTER TABLE invoice ADD COLUMN skonto_days INTEGER NOT NULL DEFAULT 0;        -- Skontofrist in Tagen

-- Mahnung: Verzugszinsen getrennt von der Mahngebühr (fee_cents existiert bereits)
ALTER TABLE reminder ADD COLUMN interest_cents INTEGER NOT NULL DEFAULT 0;

-- Einstellungen: Standardwerte für Mahnwesen und Skonto
ALTER TABLE company_settings ADD COLUMN dunning_fee_cents INTEGER NOT NULL DEFAULT 0;   -- Standard-Mahngebühr ab Stufe 2
ALTER TABLE company_settings ADD COLUMN interest_rate_bp INTEGER NOT NULL DEFAULT 0;    -- Verzugszinssatz p.a. in Basispunkten (0 = aus)
ALTER TABLE company_settings ADD COLUMN skonto_percent_bp INTEGER NOT NULL DEFAULT 0;   -- Standard-Skontosatz in Basispunkten
ALTER TABLE company_settings ADD COLUMN skonto_days INTEGER NOT NULL DEFAULT 0;         -- Standard-Skontofrist in Tagen
