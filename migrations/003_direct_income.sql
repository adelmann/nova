-- 003: Direkte Einnahmen (ohne Rechnung), z.B. Affiliate-Erlöse.
-- Werden wie Ausgaben als Stammsatz geführt und per Differenz-Buchung mit dem
-- (unveränderbaren) Buchungsjournal synchron gehalten.
CREATE TABLE income (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    income_date   TEXT NOT NULL DEFAULT (date('now')),
    source        TEXT NOT NULL DEFAULT '',          -- z.B. "Amazon PartnerNet", "Awin"
    category      TEXT NOT NULL DEFAULT 'Affiliate',  -- EÜR-Einnahmenkategorie
    project_id    INTEGER REFERENCES project(id) ON DELETE SET NULL,
    amount_cents  INTEGER NOT NULL DEFAULT 0,
    note          TEXT NOT NULL DEFAULT '',
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_income_date ON income (income_date);
CREATE INDEX idx_income_project ON income (project_id);
