-- 021: Anlagevermögen & AfA. Anlagegüter werden separat von normalen Ausgaben
-- erfasst; abzugsfähig ist nicht der Kaufpreis, sondern die jährliche
-- Abschreibung (AfA). GWG (<= 800 € netto) werden sofort voll abgeschrieben.
-- Die AfA-Buchungen landen über das Buchungsjournal automatisch in der EÜR.
CREATE TABLE asset (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    name              TEXT NOT NULL DEFAULT '',
    supplier          TEXT NOT NULL DEFAULT '',
    acquired_date     TEXT NOT NULL,
    cost_cents        INTEGER NOT NULL DEFAULT 0,
    useful_life_years INTEGER NOT NULL DEFAULT 1,
    method            TEXT NOT NULL DEFAULT 'linear',   -- linear | gwg
    note              TEXT NOT NULL DEFAULT '',
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_asset_acquired ON asset (acquired_date);
